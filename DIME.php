<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Shane Caraveo <shane@caraveo.com>                           |
// +----------------------------------------------------------------------+
//
// $Id$
//

require_once 'PEAR.php';
/**
 *
 *  DIME Encoding/Decoding
 *
 * What is it?
 *   This class enables you to manipulate and build
 *   a DIME encapsulated message.
 *
 * http://search.ietf.org/internet-drafts/draft-nielsen-dime-01.txt
 *
 * TODO: lots of stuff needs to be tested.
 *           Definitily have to go through DIME spec and
 *           make things work right, most importantly, sec 3.3
 *           make examples, document
 *
 * see test/dime_mesage_test.php for example of usage
 * 
 * @author  Shane Caraveo <shane@caraveo.com>
 * @version $Revision$
 * @package Net_DIME
 */
define('DIME_TYPE_UNCHANGED',0x00);
define('DIME_TYPE_MEDIA',0x01);
define('DIME_TYPE_URI',0x02);
define('DIME_TYPE_UNKNOWN',0x03);
define('DIME_TYPE_NONE',0x04);

define('DIME_RECORD_HEADER',8);

class DIME_Record extends PEAR
{
    // these are used to hold the padded length
    var $ID_LENGTH = 0;
    var $TYPE_LENGTH = 0; 
    var $DATA_LENGTH = 0;
    var $_haveID = FALSE;
    var $_haveType = FALSE;
    var $_haveData = FALSE;
    var $debug = FALSE;
    var $padstr = "\0";
    /**
     * _record
     * [0], 16 bits: $MB:$ME:$CF:$ID_LENGTH
     * [1], 16 bits: $TNF:$TYPE_LENGTH, $TNF defaults to DIME_TYPE_NONE
     * [2], 32 bits: $DATA_LENGTH
     * [3], ID + PADDING
     * [4], TYPE + PADDING
     * [5], DATA + PADDING
     */
    var $_record = array(0,0x8000,0,'','','');
    
    function DIME_Record($debug = FALSE)
    {
        $this->debug = $debug;
        if ($debug) $this->padstr = '*';
    }

    function setMB()
    {
        $this->_record[0] |= 0x8000;
    }

    function setME()
    {
        $this->_record[0] |= 0x4000;
    }

    function setCF()
    {
        $this->_record[0] |= 0x2000;
    }

    function isChunk()
    {
        return $this->_record[0] & 0x2000;
    }

    function isEnd()
    {
        return $this->_record[0] & 0x4000;
    }
    
    function isStart()
    {
        return $this->_record[0] & 0x8000;
    }
    
    function getID()
    {
        return $this->_record[3];
    }

    function getType()
    {
        return $this->_record[4];
    }

    function getData()
    {
        return $this->_record[5];
    }
    
    function getDataLength()
    {
        return $this->_record[2];
    }
    
    function setType($typestring, $type=DIME_TYPE_UNKNOWN)
    {
        $typelen = strlen($typestring) & 0x1FFF;
        // XXX check for overflow of type length
        $type = $type << 13;
        $this->_record[1] = $type + $typelen;
        $this->TYPE_LENGTH = $this->_getPadLength($typelen);
        $this->_record[4] = $typestring;
    }
    
    function generateID()
    {
        $id = md5(time());
        $this->setID($id);
        return $id;
    }
    
    function setID($id)
    {
        $idlen = strlen($id) & 0x1FFF;
        // XXX check for overflow error in idlen
        $flags = $this->_record[0] & 0x7000;
        $this->_record[0] = $flags + $idlen;
        $this->ID_LENGTH = $this->_getPadLength($idlen);
        $this->_record[3] = $id;
    }
    
    function setData($data, $size=0)
    {
        $datalen = $size?$size:strlen($data);
        $this->_record[2] = $datalen;
        $this->DATA_LENGTH = $this->_getPadLength($datalen);
        $this->_record[5] = $data;
    }
    
    function encode()
    {
        // create the header
        if ($this->debug) {
            // this encoding is NOT DIME!
            // it's just a bit easier to figure out problems with
            $format =   "%04X%04X%08X".
                        "%".$this->ID_LENGTH."s".
                        "%".$this->TYPE_LENGTH."s".
                        "%".$this->DATA_LENGTH."s";
            return sprintf($format,
                       ($this->_record[0]&0x0000FFFF),
                       ($this->_record[1]&0x0000FFFF),
                       $this->_record[2],
                       str_pad($this->_record[3], $this->ID_LENGTH, $this->padstr),
                       str_pad($this->_record[4], $this->TYPE_LENGTH, $this->padstr),
                       str_pad($this->_record[5], $this->DATA_LENGTH, $this->padstr));
        } else {
            // the real dime encoding
            $format =   '%c%c%c%c%c%c%c%c'.
                        '%'.$this->ID_LENGTH.'s'.
                        '%'.$this->TYPE_LENGTH.'s'.
                        '%'.$this->DATA_LENGTH.'s';
            return sprintf($format,
                       ($this->_record[0]&0x0000FF00)>>8,
                       ($this->_record[0]&0x000000FF),
                       ($this->_record[1]&0x0000FF00)>>8,
                       ($this->_record[1]&0x000000FF),
                       ($this->_record[2]&0xFF000000)>>24,
                       ($this->_record[2]&0x00FF0000)>>16,
                       ($this->_record[2]&0x0000FF00)>>8,
                       ($this->_record[2]&0x000000FF),
                       str_pad($this->_record[3], $this->ID_LENGTH, $this->padstr),
                       str_pad($this->_record[4], $this->TYPE_LENGTH, $this->padstr),
                       str_pad($this->_record[5], $this->DATA_LENGTH, $this->padstr));
        }
        
    }
    
    function _getPadLength($len)
    {
        $pad = 0;
        if ($len) {
            $pad = $len % 32;
            if ($pad) $pad = 32 - $pad;
        }
        return $len + $pad;
    }
    
    function decode(&$data)
    {
        if ($this->debug) {
            echo " data length is: ".strlen($data)."\n";
            // debug decoding against our own format
            $this->_record[0] = hexdec(substr($data,0,4));
            $this->_record[1] = hexdec(substr($data,4,4));
            $this->_record[2] = hexdec(substr($data,8,8));
            $p = 16;
        } else {
            // REAL DIME decoding
            $this->_record[0] = (hexdec(bin2hex($data[0]))<<8) + hexdec(bin2hex($data[1]));
            $this->_record[1] = (hexdec(bin2hex($data[2]))<<8) + hexdec(bin2hex($data[3]));
            $this->_record[2] = (hexdec(bin2hex($data[4]))<<24) +
                                (hexdec(bin2hex($data[5]))<<16) +
                                (hexdec(bin2hex($data[6]))<<8) +
                                hexdec(bin2hex($data[7]));
            $p = 8;
        }
        
        $this->id_len = $this->_record[0] & 0x1FFF;
        $this->ID_LENGTH = $this->_getPadLength($this->id_len);
        $this->type_len = $this->_record[1] & 0x1FFF;
        $this->TYPE_LENGTH = $this->_getPadLength($this->type_len);
        $this->DATA_LENGTH = $this->_getPadLength($this->_record[2]);
        
        if ($this->debug) {
            echo " idlen: $this->id_len bytes padded: $this->ID_LENGTH\n";
            echo " typelen: $this->type_len bytes padded: $this->TYPE_LENGTH\n";
            echo " datalen: {$this->_record[2]} bytes padded: $this->DATA_LENGTH\n";
        }
        
        $datalen = strlen($data);
        $this->_record[3] = substr($data,$p,$this->id_len);
        $this->_haveID = (strlen($this->_record[3]) == $this->id_len);
        if ($this->_haveID) {
            $p += $this->ID_LENGTH;
            $this->_record[4] = substr($data,$p,$this->type_len);
            $this->_haveType = (strlen($this->_record[4]) == $this->type_len);
            if ($this->_haveType) {
                $p += $this->TYPE_LENGTH;
                $this->_record[5] = substr($data,$p,$this->_record[2]);
                $this->_haveData = (strlen($this->_record[5]) == $this->_record[2]);
                if ($this->_haveData) {
                    $p += $this->DATA_LENGTH;
                } else {
                    $p += strlen($this->_record[5]);
                }
            } else {
                $p += strlen($this->_record[4]);
            }
        } else {
            $p += strlen($this->_record[3]);
        }
        return substr($data, $p);
    }
    
    function addData(&$data)
    {
        $datalen = strlen($data);
        $p = 0;
        if (!$this->_haveID) {
            $have = strlen($this->_record[3]);
            $this->_record[3] .= substr($data,$p,$this->id_len-$have);
            $this->_haveID = (strlen($this->_record[3]) == $this->id_len);
            if (!$this->_haveID) return NULL;
            $p += $this->ID_LENGTH-$have;
        }
        if (!$this->_haveType && $p < $datalen) {
            $have = strlen($this->_record[4]);
            $this->_record[4] .= substr($data,$p,$this->type_len-$have);
            $this->_haveType = (strlen($this->_record[4]) == $this->type_len);
            if (!$this->_haveType) return NULL;
            $p += $this->TYPE_LENGTH-$have;
        }
        if (!$this->_haveData && $p < $datalen) {
            $have = strlen($this->_record[5]);
            $this->_record[5] .= substr($data,$p,$this->_record[2]-$have);
            $this->_haveData = (strlen($this->_record[5]) == $this->_record[2]);
            if (!$this->_haveData) return NULL;
            $p += $this->DATA_LENGTH-$have;
        }
        return substr($data,$p);
    }
}


class DIME_Message extends PEAR
{

    var $record_size = 4096;
    #var $records =array();
    var $parts = array();
    var $currentPart = -1;
    var $stream = NULL;
    var $_currentRecord;
    var $_proc = array();
    var $type;
    var $typestr;
    var $mb = 1;
    var $me = 0;
    var $cf = 0;
    var $id = NULL;
    var $debug = FALSE;
    /**
     * constructor
     *
     * this currently takes a file pointer as provided
     * by fopen
     *
     * TODO: integrate with the php streams stuff
     */
    function DIME_Message($stream, $record_size = 4096, $debug = FALSE)
    {
        $this->stream = $stream;
        $this->record_size = $record_size;
        $this->debug = $debug;
    }
    
    function _makeRecord(&$data, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $record = new DIME_Record($this->debug);
        if ($this->mb) {
            $record->setMB();
            // all subsequent records are not message begin!
            $this->mb = 0; 
        }
        if ($this->me) $record->setME();
        if ($this->cf) $record->setCF();
        $record->setData($data);
        $record->setType($typestr,$type);

        #if ($this->debug) {
        #    print str_replace('\0','*',$record->encode());
        #}
        return $record->encode();
    }
    
    function startChunk(&$data, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $this->me = 0;
        $this->cf = 1;
        $this->type = $type;
        $this->typestr = $typestr;
        if ($id) {
            $this->id = $id;
        } else {
            $this->id = md5(time());
        }
        return $this->_makeRecord($data, $this->typestr, $this->id, $this->type);
    }

    function doChunk(&$data)
    {
        $this->me = 0;
        $this->cf = 1;
        return $this->_makeRecord($data, NULL, NULL, DIME_TYPE_UNCHANGED);
    }

    function endChunk()
    {
        $this->cf = 0;
        $data = NULL;
        $rec = $this->_makeRecord($data, NULL, NULL, DIME_TYPE_UNCHANGED);
        $this->id = 0;
        $this->cf = 0;
        $this->id = 0;
        $this->type = DIME_TYPE_UNKNOWN;
        $this->typestr = NULL;
        return $rec;
    }
    
    function endMessage()
    {
        $this->me = 1;
        $data = NULL;
        $rec = $this->_makeRecord($data, NULL, NULL, DIME_TYPE_NONE);
        $this->me = 0;
        $this->mb = 1;
        $this->id = 0;
        return $rec;
    }
    
    /**
     * sendRecord
     *
     * given a chunk of data, it creates DIME records
     * and writes them to the stream
     *
     */
    function sendData(&$data, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $len = strlen($data);
        if ($len > $this->record_size) {
            $chunk = substr($data, 0, $this->record_size);
            $p = $this->record_size;
            $rec = $this->startChunk($chunk,$typestr,$id,$type);
            fwrite($this->stream, $rec);
            while ($p < $len) {
                $chunk = substr($data, $p, $this->record_size);
                $p += $this->record_size;
                $rec = $this->doChunk($chunk);
                fwrite($this->stream, $rec);
            }
            $rec = $this->endChunk();
            fwrite($this->stream, $rec);
            return;
        }
        $rec = $this->_makeRecord($data, $typestr,$id,$type);
        fwrite($this->stream, $rec);
    }
    
    function sendEndMessage()
    {
        $rec = $this->endMessage();
        fwrite($this->stream, $rec);
    }
    
    /**
     * sendFile
     *
     * given a filename, it reads the file,
     * creates records and writes them to the stream
     *
     */
    function sendFile($filename, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $f = fopen($filename, "rb");
        if ($f) {
            if ($data = fread($f, $this->record_size)) {
                $this->startChunk($data,$typestr,$id,$type);
            }
            while ($data = fread($f, $this->record_size)) {
                $this->doChunk($data,$typestr,$id,$type);
            }
            $this->endChunk();
            fclose($f);
        }
    }

    /**
     * encodeData
     *
     * given data, encode it in DIME
     *
     */
    function encodeData($data, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $len = strlen($data);
        $resp = '';
        if ($len > $this->record_size) {
            $chunk = substr($data, 0, $this->record_size);
            $p = $this->record_size;
            $resp .= $this->startChunk($chunk,$typestr,$id,$type);
            while ($p < $len) {
                $chunk = substr($data, $p, $this->record_size);
                $p += $this->record_size;
                $resp .= $this->doChunk($chunk);
            }
            $resp .= $this->endChunk();
        } else {
            $resp .= $this->_makeRecord($data, $typestr,$id,$type);
        }
        return $resp;
    }

    /**
     * sendFile
     *
     * given a filename, it reads the file,
     * creates records and writes them to the stream
     *
     */
    function encodeFile($filename, $typestr='', $id=NULL, $type=DIME_TYPE_UNKNOWN)
    {
        $f = fopen($filename, "rb");
        if ($f) {
            if ($data = fread($f, $this->record_size)) {
                $resp = $this->startChunk($data,$typestr,$id,$type);
            }
            while ($data = fread($f, $this->record_size)) {
                $resp = $this->doChunk($data,$typestr,$id,$type);
            }
            $resp = $this->endChunk();
            fclose($f);
        }
        return $resp;
    }
    
    /**
     * _processData
     *
     * creates DIME_Records from provided data
     *
     */
    function _processData(&$data)
    {
        $leftover = NULL;
        if (!$this->_currentRecord) {
            $this->_currentRecord = new DIME_Record($this->debug);
            $data = $this->_currentRecord->decode($data);
        } else {
            $data = $this->_currentRecord->addData($data);
        }
        if ($this->_currentRecord->_haveData) {
            if ($this->debug) {
                echo "  idlen: ".strlen($this->_currentRecord->_record[3])."\n";
                echo "  typelen: ".strlen($this->_currentRecord->_record[4])."\n";
                echo "  datalen: ".strlen($this->_currentRecord->_record[5])."\n";
            }
            if (count($this->parts)==0 && !$this->_currentRecord->isStart()) {
                // raise an error!
                return PEAR::raiseError('First Message is not a DIME begin record!');
            }

            if ($this->_currentRecord->isEnd() && $this->_currentRecord->getDataLength()==0) {
                return NULL;
            }
            
            if ($this->currentPart < 0 && !$this->_currentRecord->isChunk()) {
                $this->parts[] = array();
                $this->currentPart = count($this->parts)-1;
                $this->parts[$this->currentPart]['id'] = $this->_currentRecord->getID();
                $this->parts[$this->currentPart]['type'] = $this->_currentRecord->getType();
                $this->parts[$this->currentPart]['data'] = $this->_currentRecord->getData();
                $this->currentPart = -1;
            } else {
                if ($this->currentPart < 0) {
                    $this->parts[] = array();
                    $this->currentPart = count($this->parts)-1;
                    $this->parts[$this->currentPart]['id'] = $this->_currentRecord->getID();
                    $this->parts[$this->currentPart]['type'] = $this->_currentRecord->getType();
                    $this->parts[$this->currentPart]['data'] = $this->_currentRecord->getData();
                } else {
                    $this->parts[$this->currentPart]['data'] .= $this->_currentRecord->getData();
                    if (!$this->_currentRecord->isChunk()) {
                        // we reached the end of the chunk
                        $this->currentPart = -1;
                    }
                }
            }
            #$this->records[] = $this->_currentRecord;
            if (!$this->_currentRecord->isEnd()) $this->_currentRecord = NULL;
        }
        return NULL;
    }
    
    /**
     * decodeData
     *
     * decodes a DIME encrypted string of data
     *
     */
    function decodeData(&$data) {
        while (strlen($data) >= DIME_RECORD_HEADER) {
            $err = $this->_processData($data);
            if (PEAR::isError($err)) {
                return $err;
            }
        }
    }
    
    /**
     * read
     *
     * reads the stream and creates
     * an array of records
     *
     * it can accept the start of a previously read buffer
     * this is usefull in situations where you need to read
     * headers before discovering that the data is DIME encoded
     * such as in the case of reading an HTTP response.
     */
    function read($buf=NULL)
    {
        while ($data = fread($this->stream, 8192)) {
            if ($buf) {
                $data = $buf.$data;
                $buf = NULL;
            }
            if ($this->debug)
                echo "read: ".strlen($data)." bytes\n";
            $err = $this->decodeData($data);
            if (PEAR::isError($err)) {
                return $err;
            }
            
            // store any leftover data to be used again
            // should be < DIME_RECORD_HEADER bytes
            $buf = $data;
        }
        if (!$this->_currentRecord || !$this->_currentRecord->isEnd()) {
            return PEAR::raiseError('reached stream end without end record');
        }
        return NULL;
    }
}
?>
