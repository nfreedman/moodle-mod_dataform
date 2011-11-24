<?php

/**
 * This file is part of the Dataform module for Moodle - http://moodle.org/.
 *
 * @package mod-dataform
 * @subpackage field-file
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Dataform has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Dataform code corresponds to Database code,
 * certain copyrights on the Database module may obtain, including:
 * @copyright 1999 Moodle Pty Ltd http://moodle.com
 *
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 */

require_once("$CFG->dirroot/mod/dataform/field/field_class.php");

class dataform_field_file extends dataform_field_base {
    public $type = 'file';

    /**
     *
     */
    public function update_content($entry, array $values = null) {
        global $DB, $USER;

        $entryid = $entry->id;
        $fieldid = $this->field->id;

        $filemanager = $alttext = $delete = $editor = null;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                $names = explode('_', $name);
                if (!empty($names[3]) and !empty($value)) {
                    ${$names[3]} = $value;
                }
            }
        }

        // update file content
        if ($editor) {
            return $this->save_changes_to_file($entry, $values);
        }
            
        // delete files
        //if ($delete) {
        //    return $this->delete_content($entryid);
        //}
        
        // store uploaded files
        $contentid = isset($entry->{"c{$this->field->id}_id"}) ? $entry->{"c{$this->field->id}_id"} : null;
        $draftarea = $filemanager;
        $usercontext = get_context_instance(CONTEXT_USER, $USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftarea);
        if (count($files)>1) {
            // there are files to upload so add/update content record
            $rec = new object;
            $rec->fieldid = $fieldid;
            $rec->entryid = $entryid;
            $rec->content = 1;
            $rec->content1 = $alttext;

            if (!empty($contentid)) {
                $rec->id = $contentid;
                $DB->update_record('dataform_contents', $rec);
            } else {
                $contentid = $DB->insert_record('dataform_contents', $rec);
            }
            
            // now save files
            $options = array('subdirs' => 0,
                                'maxbytes' => $this->field->param1,
                                'maxfiles' => $this->field->param2,
                                'accepted_types' => $this->field->param3);
            $contextid = $this->df->context->id;
            file_save_draft_area_files($filemanager, $contextid, 'mod_dataform', 'content', $contentid, $options);

            $this->update_content_files($contentid);

        // user cleared files from the field
        } else if (!empty($contentid)) {
            $this->delete_content($entryid);
        }
        return true;
    }

    /**
     *
     */
    public function format_content(array $values = null) {
        return array(null, null, null);
    }

    /**
     *
     */
    public function get_select_sql() {
        $id = " c{$this->field->id}.id AS c{$this->field->id}_id ";
        $content = $this->get_sql_compare_text(). " AS c{$this->field->id}_content";
        $content1 = " c{$this->field->id}.content1 AS c{$this->field->id}_content1";
        return " $id , $content , $content1 ";
    }

    /**
     *
     */
    public static function file_ok($path) {
        return true;
    }

    /**
     *
     */
    public function prepare_import_content(&$data, $patterns, $formdata) {
        global $USER;
    
        $fieldid = $this->field->id;
        foreach ($patterns as $tag) {
            $tagname = trim($tag, "[]#");
            
            if ($tagname == $this->name()) {
                // get the uploaded images file
                $draftid = $formdata->{"f_{$fieldid}_{$tagname}_filepicker"};
                $usercontext = get_context_instance(CONTEXT_USER, $USER->id);
                $fs = get_file_storage();
                if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'sortorder', false)) {
                    $zipfile = reset($files);
                    // extract files to the draft area
                    $zipper = get_file_packer('application/zip');
                    $zipfile->extract_to_storage($zipper, $usercontext->id, 'user', 'draft', $draftid, '/');
                    $zipfile->delete();
                
                    // move each file to its own area and add info to data
                    if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'sortorder', false)) {
                        $rec = new object;
                        $rec->contextid = $usercontext->id;
                        $rec->component = 'user';
                        $rec->filearea = 'draft';

                        $i = 0;
                        foreach ($files as $file) {
                            //if ($file->is_valid_image()) {
                                // $get unused draft area
                                $itemid = file_get_unused_draft_itemid();
                                // move image to the new draft area 
                                $rec->itemid = $itemid;
                                $fs->create_file_from_storedfile($rec, $file);
                                // add info to data
                                $i--;
                                $fieldname = "field_{$fieldid}_$i";
                                $data->{"{$fieldname}_filemanager"} = $itemid;
                                $data->{"{$fieldname}_alttext"} = $file->get_filename();
                                $data->eids[$i] = $i;
                            //}
                        }
                        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftid);
                    }
                }
            }
        }
        
        return $data;        
    }

    /**
     *
     */
    protected function update_content_files($contentid, $params = null) {
        return true;
    }

            
    /**
     *
     */
    protected function save_changes_to_file($entry, array $values = null) {

        $fieldid = $this->field->id;
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entry->id}";

        $contentid = isset($entry->{"c{$this->field->id}_id"}) ? $entry->{"c{$this->field->id}_id"} : null;

        $options = array('context' => $this->df->context);
        $data = (object) $values;
        $data = file_postupdate_standard_editor((object) $values, $fieldname, $options, $this->df->context, 'mod_dataform', 'content', $contentid);

        // get the file content
        $fs = get_file_storage();
        $file = reset($fs->get_area_files($this->df->context->id, 'mod_dataform', 'content', $contentid, 'sortorder', false));
        $filecontent = $file->get_content();
        
        // find content position (between body tags)
        $tmpbodypos = stripos($filecontent, '<body');
        $openbodypos = strpos($filecontent, '>', $tmpbodypos) + 1;
        $sublength = strripos($filecontent, '</body>') - $openbodypos;
        
        // replace body content with new content
        $filecontent = substr_replace($filecontent, $data->$fieldname, $openbodypos, $sublength);

        // prepare new file record
        $rec = new object;
        $rec->contextid = $this->df->context->id;
        $rec->component = 'mod_dataform';
        $rec->filearea = 'content';
        $rec->itemid = $contentid;
        $rec->filename = $file->get_filename();
        $rec->filepath = '/';
        $rec->timecreated = $file->get_timecreated();
        $rec->userid = $file->get_userid();
        $rec->source = $file->get_source();
        $rec->author = $file->get_author();
        $rec->license = $file->get_license();
        
        // delete old file
        $fs->delete_area_files($this->df->context->id, 'mod_dataform', 'content', $contentid);
        
        // create a new file from string
        $fs->create_file_from_string($rec, $filecontent);
        return true;           
    }
}