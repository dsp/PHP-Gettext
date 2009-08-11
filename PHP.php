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
    protected $origTable = array();
    protected $transTable = array();
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
        $fp     = fopen($this->mofile, "rb");
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

        /* more tests */
        if (fseek($fp, $offsets['orig_offset'], SEEK_SET) < 0) {
            throw new Exception ("Error seeking offset");
        }

        /* prefatch */
        $offsetTableOriginal = array();
        if ($filesize < 4 * ($offsets['num_strings'] + 7)) {
            throw new Exception('File is too small');
        }
        for ($i = 0; $i < $offsets['num_strings']; $i++) {
            $data                  = fread($fp, 8);
            $offsetTableOriginal[] = unpack("lsize/loffset", $data);
        }

        $offsetTableTranslations = array();
        for ($i = 0; $i < $offsets['num_strings']; $i++) {
            $data                      = fread($fp, 8);
            $offsetTableTranslations[] = unpack("lsize/loffset", $data);
        }

        $idx = 0;
        $this->origTable = array();
        foreach ($offsetTableOriginal as $entry) {
            if (fseek($fp, $entry['offset'], SEEK_SET) < 0) {
                fclose($fp);
                throw new Exception ("Error seeking offset");
            }
            if ($entry['size'] > 0) {
                $entry['string'] = fread($fp, $entry['size']);
                $entry['index']  = $idx;

                $this->origTable[$entry['string']] = $entry;
            }
            $idx++;
        }

        $idx = 0;
        $this->transTable = array();
        foreach ($offsetTableTranslations as $entry) {
            if (fseek($fp, $entry['offset'], SEEK_SET) < 0) {
                fclose($fp);
                throw new Exception ("Error seeking offset");
            }
            if ($entry['size'] > 0) {
                $entry['string'] = fread($fp, $entry['size']);

                $this->transTable[$idx] = $entry;
            }
            $idx++;
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

        if (array_key_exists($msg, $this->origTable)) {
            $idx = $this->origTable[$msg]['index'];
            if (array_key_exists($idx, $this->transTable)) {
                return $this->transTable[$idx]['string'];
            }
        }
        return $msg;
    }

    public function ngettext($msg1, $msg2, $count) {
        if (!$this->parsed) {
            $this->parse();
        }
        $k = $msg1 . chr(0) . $msg2;
        if (array_key_exists($k, $this->origTable)) {
            $idx   = $this->origTable[$k]['index'];
            $entry = $this->transTable[$idx]['string'];
            $msg   = explode(chr(0), $entry);
            if (count($msg) < $count - 1) {
                return $msg1;
            }

            return $msg[$count - 1];
        }

        return $msg;
    }
}

