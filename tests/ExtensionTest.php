<?php
require_once 'PHPUnit/Framework.php';
require_once '../Gettext.php';

class ExtensionTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->fixture = new \Gettext\Implementation\Extension('./', 'gettext', 'de');
    }

    public function testGettext()
    {
        $this->assertEquals('Datei existiert nicht', $this->fixture->gettext('File does not exist'));
        $this->assertEquals('Datei ist zu klein', $this->fixture->gettext('File is too small'));
        $this->assertEquals('Foobar', $this->fixture->gettext('Foobar'));
        $this->assertContains('Last-Translator', $this->fixture->gettext(null));
    }

    public function testNgettext()
    {
        $this->assertEquals('Datei existiert nicht', $this->fixture->ngettext('File does not exist', 'Files donnot exists', 1));
        $this->assertEquals('Datei ist zu klein', $this->fixture->ngettext('File is too small', 'Files are too small', 1));
        $this->assertEquals('Foobar', $this->fixture->ngettext('Foobar', 'Foobar', 1));

        $this->assertEquals('Datei existiert nicht', $this->fixture->ngettext('File does not exist', 'Files donnot exists', 2));
        $this->assertEquals('Dateien sind zu klein', $this->fixture->ngettext('File is too small', 'Files are too small', 2));
        $this->assertEquals('Foobar', $this->fixture->ngettext('Foobar', 'Foobar', 2));

        $this->assertContains('Last-Translator', $this->fixture->ngettext(null, null, 1));

        $this->assertEquals('Dateien sind zu klein', $this->fixture->ngettext('File is too small', 'Files are too small', -1));
    }
}

