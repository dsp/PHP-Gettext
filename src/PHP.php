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
namespace gettext;

/**
 * Gettext implementation in PHP
 *
 * @copyright (c) 2009 David Soria Parra <sn_@gmx.net>
 * @author David Soria Parra <sn_@gmx.net>
 */
class PHP extends Gettext
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
    protected $translationTable = [];
    protected $parsed = false;

    /**
     * Initialize a new gettext class
     *
     * @param string $directory
     * @param string $domain
     * @param string $locale
     */
    public function __construct($directory, $domain, $locale)
    {
        $this->mofile = sprintf(
            "%s/%s/LC_MESSAGES/%s.mo",
            $directory,
            $locale,
            $domain
        );
    }

    /**
     * Parse the MO file header and returns the table
     * offsets as described in the file header.
     *
     * If an exception occured, null is returned. This is intentionally
     * as we need to get close to ext/gettexts beahvior.
     *
     * @param resource $resource The open file handler to the MO file
     *
     * @return array An array of offset
     */
    private function parseHeader($resource)
    {
        $data = fread($resource, 8);
        $header = unpack("lmagic/lrevision", $data);

        if ((int)self::MAGIC1 != $header['magic']
            && (int)self::MAGIC2 != $header['magic']
        ) {
            return null;
        }

        if (0 != $header['revision']) {
            return null;
        }

        $data = fread($resource, 4 * 5);
        $offsets = unpack("lnum_strings/lorig_offset/"
            . "ltrans_offset/lhash_size/lhash_offset", $data);

        return $offsets;
    }

    /**
     * Parse and reutnrs the string offsets in a a table. Two table can be found in
     * a mo file. The table with the translations and the table with the original
     * strings. Both contain offsets to the strings in the file.
     *
     * If an exception occured, null is returned. This is intentionally
     * as we need to get close to ext/gettexts beahvior.
     *
     * @param resource $resource The open file handler to the MO file
     * @param Integer $offset The offset to the table that should be parsed
     * @param Integer $num The number of strings to parse
     *
     * @return array of offsets
     */
    private function parseOffsetTable($resource, $offset, $num)
    {
        if (fseek($resource, $offset, SEEK_SET) < 0) {
            return null;
        }

        $table = [];
        for ($i = 0; $i < $num; $i++) {
            $data = fread($resource, 8);
            $table[] = unpack("lsize/loffset", $data);
        }

        return $table;
    }

    /**
     * Parse a string as referenced by an table. Returns an
     * array with the actual string.
     *
     * @param resource $resource The open file handler to the MO fie
     * @param array $entry The entry as parsed by parseOffsetTable()
     *
     * @return string Parsed string
     */
    private function parseEntry($resource, $entry)
    {
        if (fseek($resource, $entry['offset'], SEEK_SET) < 0) {
            return null;
        }
        if ($entry['size'] > 0) {
            return fread($resource, $entry['size']);
        }

        return '';
    }

    /**
     * Parse the MO file
     *
     * @return void
     */
    private function parse()
    {
        $this->translationTable = [];

        if (!file_exists($this->mofile)) {
            return;
        }

        $filesize = filesize($this->mofile);
        if ($filesize < 4 * 7) {
            return;
        }

        /* check for filesize */
        $resource = fopen($this->mofile, "rb");

        $offsets = $this->parseHeader($resource);
        if (null == $offsets || $filesize < 4 * ($offsets['num_strings'] + 7)) {
            fclose($resource);

            return;
        }

        $transTable = [];
        $table = $this->parseOffsetTable(
            $resource,
            $offsets['trans_offset'],
            $offsets['num_strings']
        );
        if (null == $table) {
            fclose($resource);

            return;
        }

        foreach ($table as $idx => $entry) {
            $transTable[$idx] = $this->parseEntry($resource, $entry);
        }

        $table = $this->parseOffsetTable(
            $resource,
            $offsets['orig_offset'],
            $offsets['num_strings']
        );
        foreach ($table as $idx => $entry) {
            $entry = $this->parseEntry($resource, $entry);

            $formes = explode(chr(0), $entry);
            $translation = explode(chr(0), $transTable[$idx]);
            foreach ($formes as $form) {
                $this->translationTable[$form] = $translation;
            }
        }

        fclose($resource);

        $this->parsed = true;
    }

    /**
     * Return a translated string
     *
     * If the translation is not found, the original passed message
     * will be returned.
     *
     * @param string $msg
     *
     * @return string Translated message
     */
    public function gettext($msg)
    {
        if (!$this->parsed) {
            $this->parse();
        }

        if (array_key_exists($msg, $this->translationTable)) {
            return $this->translationTable[$msg][0];
        }

        return $msg;
    }

    /**
     * Return a translated string in it's plural form
     *
     * Returns the given $count (e.g second, third,...) plural form of the
     * given string. If the id is not found and $num == 1 $msg is returned,
     * otherwise $msg_plural
     *
     * @param String $msg The message to search for
     * @param String $msg_plural A fallback plural form
     * @param Integer $count Which plural form
     *
     * @return string Translated string
     */
    public function ngettext($msg, $msg_plural, $count)
    {
        if (!$this->parsed) {
            $this->parse();
        }

        $msg = (string)$msg;

        if (array_key_exists($msg, $this->translationTable)) {
            $translation = $this->translationTable[$msg];
            /* the gettext api expect an unsigned int, so we just fake 'cast' */
            if ($count <= 0 || count($translation) < $count) {
                $count = count($translation);
            }

            return $translation[$count - 1];
        }

        /* not found, handle count */
        if (1 == $count) {
            return $msg;
        } else {
            return $msg_plural;
        }
    }
}
