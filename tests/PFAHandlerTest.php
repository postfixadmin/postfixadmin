<?php

class PFAHandlerTest extends \PHPUnit\Framework\TestCase
{

    /**
     * Just checking that the rename of the 9th parameter ($options) to become $not_in_db behaves
     * Previously if the param was an array, it trampled on any params in scope.
     */
    public function testPaColNotInDb()
    {

        $expected = [
            "editable" => 1,
            "display_in_form" => 1,
            "display_in_list" => 1,
            "type" => "text",
            "label" => "",
            "desc" => "",
            "default" => "test",
            "options" => [],
            "not_in_db" => 1,
            "dont_write_to_db" => 0,
            "select" => "test",
            "extrafrom" => "test",
            "linkto" => "test",
        ];

        $this->assertEquals($expected, PFAHandler::pacol(1, 1, 1, 'text', 'test', 'test', 'test', [], 1, 0, 'test', 'test', 'test'));


        $expected = [
            "editable" => 1,
            "display_in_form" => 1,
            "display_in_list" => 1,
            "type" => "text",
            "label" => "",
            "desc" => "",
            "default" => "test",
            "options" => [],
            "not_in_db" => 0,
            "dont_write_to_db" => 0,
            "select" => "fish",
            "extrafrom" => "",
            "linkto" => "",
        ];

        $this->assertEquals($expected, PFAHandler::pacol(1, 1, 1, 'text', 'test', 'test', 'test', [], ['not_in_db' => 0, 'select' => 'fish']));

    }
}