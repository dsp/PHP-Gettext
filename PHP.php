<?php
/*
 * Copyright (c) 2009 David Soria Parra
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */


/**
 * Gettext implementation in PHP
 *
 * @copyright (c) 2009 David Soria Parra <sn_@gmx.net>
 * @author David Soria Parra <sn_@gmx.net>
 */
class Gettext_PHP extends Gettext
{
    /**
     * First magic word in the MO header
     */
    const MAGIC1 = 0xde120495;

    /**
     * First magic word in the MO header
     */
    const MAGIC2 = 0x950412de;

    protected $mofile;
    protected $translationTable = array();
    protected $revision = 0;
    protected $parsed = false;

    /**
     * Initialize a new gettext class
     *
     * @param String $mofile The file to parse
     */
    public function __construct($directory, $domain, $locale) {
        $this->mofile = sprintf("%s/%s/LC_MESSAGES/%s.mo", $directory, $locale, $domain);
    }

    private function parseHeader($fp){
        $data   = fread($fp, 8);
        $header = unpack("imagic/irevision", $data);

        if ($header['magic'] != (int) self::MAGIC1
           && $header['magic'] != (int) self::MAGIC2) {
            fclose($fp);
            throw new Exception ("Not a gettext file");
        }

        if ($header['revision'] != 0) {
            fclose($fp);
            throw new Exception ("Unsupported version");
        }

        $this->revision = $header['revision'];

        $data    = fread($fp, 4 * 5);
        $offsets = unpack("inum_strings/iorig_offset/itrans_offset/ihash_size/ihash_offset", $data);
        return $offsets;
    }

    private function parseOffsetTable($fp, $offset, $num) {
        if (fseek($fp, $offset, SEEK_SET) < 0) {
            throw new Exception ("Error seeking offset");
        }

        $table = array();
        for ($i = 0; $i < $num; $i++) {
            $data    = fread($fp, 8);
            $table[] = unpack("lsize/loffset", $data);
        }

        return $table;
    }

    private function parseEntry($fp, $entry) {
        if (fseek($fp, $entry['offset'], SEEK_SET) < 0) {
            fclose($fp);
            throw new Exception ("Error seeking offset");
        }
        if ($entry['size'] > 0) {
            return fread($fp, $entry['size']);
        }

       return '';
    }


    /**
     * Parse the MO file
     *
     * @return void
     */
    private function parse() {
        if (!file_exists($this->mofile)) {
            throw new Exception ("File does not exist");
        }

        $filesize = filesize($this->mofile);
        if ($filesize < 4 * 7) {
            throw new Exception('File is too small');
        }
    
        /* check for filesize */
        $fp = fopen($this->mofile, "rb");

        $offsets = $this->parseHeader($fp);
        if ($filesize < 4 * ($offsets['num_strings'] + 7)) {
            throw new Exception('File is too small');
        }

        $transTable = array();
        $table = $this->parseOffsetTable($fp, $offsets['trans_offset'],
                        $offsets['num_strings']);
        foreach ($table as $idx => $entry) {
            $transTable[$idx] = $this->parseEntry($fp, $entry);
        }

        $this->translationTable = array();

        $this->origTable = array();
        $table = $this->parseOffsetTable($fp, $offsets['orig_offset'],
                        $offsets['num_strings']);
        foreach ($table as $idx => $entry) {
            $entry = $this->parseEntry($fp, $entry);

            $formes      = explode(chr(0), $entry);
            $translation = explode(chr(0), $transTable[$idx]);
            foreach($formes as $form) {
                $this->translationTable[$form] = $translation;
            }
        }

        fclose($fp);

        $this->parsed = true;
    }

    /**
     * Return a translated string
     *
     * If the translation is not found, the original passed message
     * will be returned.
     *
     * @return Translated message
     */
    public function gettext($msg) {
        if (!$this->parsed) {
            $this->parse();
        }

        if (array_key_exists($msg, $this->translationTable)) {
            return $this->translationTable[$msg][0];
        }
        return $msg;
    }

    public function ngettext($msg, $msg_plural, $count) {
        if (!$this->parsed) {
            $this->parse();
        }

        $msg   = (string) $msg;

        if (array_key_exists($msg, $this->translationTable)) {
            $translation = $this->translationTable[$msg];
            /* the gettext api expect an unsigned int, so we just fake 'cast' */
            if ($count < 0 || count($translation) < $count) {
                $count = count($translation);
            }
            return $translation[$count - 1];
        }

        /* not found, handle count */
        if ($count == 1) {
            return $msg;
        } else {
            return $msg_plural;
        }
    }
}

