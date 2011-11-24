<?php

/**
 * This file is part of the Dataform module for Moodle - http://moodle.org/.
 *
 * @package mod-dataform
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Dataform has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Dataform code corresponds to Database code,
 * certain copyrights on the Database module may obtain.
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

require_once('../../config.php');
require_once('mod_class.php');

$urlparams = new object();

$urlparams->d          = optional_param('d', 0, PARAM_INT);             // dataform id
$urlparams->id         = optional_param('id', 0, PARAM_INT);            // course module id
$urlparams->fid        = optional_param('fid', 0 , PARAM_INT);          // update filter id

// filters list actions
$urlparams->new        = optional_param('new', 0, PARAM_INT);     // new filter

$urlparams->show       = optional_param('show', 0, PARAM_INT);     // filter show/hide flag
$urlparams->hide       = optional_param('hide', 0, PARAM_INT);     // filter show/hide flag
$urlparams->fedit     = optional_param('fedit', 0, PARAM_SEQUENCE);   // ids (comma delimited) of filters to delete
$urlparams->delete     = optional_param('delete', 0, PARAM_SEQUENCE);   // ids (comma delimited) of filters to delete
$urlparams->duplicate  = optional_param('duplicate', 0, PARAM_SEQUENCE);   // ids (comma delimited) of filters to duplicate

$urlparams->confirmed    = optional_param('confirmed', 0, PARAM_INT);    

// filter actions
$urlparams->update     = optional_param('update', 0, PARAM_INT);   // update filter
$urlparams->cancel     = optional_param('cancel', 0, PARAM_BOOL);

// Set a dataform object
$df = new dataform($urlparams->d, $urlparams->id);
require_capability('mod/dataform:managetemplates', $df->context);

$df->set_page('filters', array('modjs' => true, 'urlparams' => $urlparams));

// activate navigation node
navigation_node::override_active_url(new moodle_url('/mod/dataform/filters.php', array('id' => $df->cm->id)));

// DATA PROCESSING
if ($urlparams->duplicate and confirm_sesskey()) {  // Duplicate any requested filters
    $df->process_filters('duplicate', $urlparams->duplicate, $urlparams->confirmed);

} else if ($urlparams->delete and confirm_sesskey()) { // Delete any requested filters
    $df->process_filters('delete', $urlparams->delete, $urlparams->confirmed);

} else if ($urlparams->show and confirm_sesskey()) {    // set filter to visible
    $df->process_filters('show', $urlparams->show, true);    // confirmed by default

} else if ($urlparams->hide and confirm_sesskey()) {   // set filter to visible
    $df->process_filters('hide', $urlparams->hide, true);    // confirmed by default

} else if ($urlparams->update and confirm_sesskey()) {  // Add/update a new filter
    $df->process_filters('update', $urlparams->fid, true);
}

//  edit a new filter
if ($urlparams->new and confirm_sesskey()) {    
    $filter = $df->get_filter_from_id();
    $df->display_filter_form($filter);

// (or) edit existing filter
} else if ($urlparams->fedit and confirm_sesskey()) {  
    $filter = $df->get_filter_from_id($urlparams->fedit);
    $df->display_filter_form($filter);

// (or) display the filters list
} else {    
    // any notifications?
    if (!$filters = $df->get_filters()) {
        $df->notifications['bad'][] = get_string('filtersnoneindataform','dataform');  // nothing in dataform
        $df->notifications['bad'][] = get_string('pleaseaddsome','dataform', 'packages.php?d='.$df->id());      // link to packages
    }

    // print header
    $df->print_header(array('tab' => 'filters', 'urlparams' => $urlparams));

    // display the filter add link
    echo html_writer::empty_tag('br');
    echo html_writer::start_tag('div', array('class'=>'fieldadd mdl-align'));
    echo html_writer::link(new moodle_url('/mod/dataform/filters.php', array('d' => $df->id(), 'sesskey' => sesskey(), 'new' => 1)), get_string('filteradd','dataform'));
    //echo $OUTPUT->help_icon('filteradd', 'dataform');
    echo html_writer::end_tag('div');
    echo html_writer::empty_tag('br');

    // if there are filters print admin style list of them
    if ($filters) {

        $filterbaseurl = '/mod/dataform/filters.php';
        $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());
                        
        // table headings
        $strfilters = get_string('name');
        $strdescription = get_string('description');
        $strperpage = get_string('filterperpage', 'dataform');
        $strcustomsort = get_string('filtercustomsort', 'dataform');
        $strcustomsearch = get_string('filtercustomsearch', 'dataform');
        $strvisible = get_string('visible');
        $strhide = get_string('hide');
        $strshow = get_string('show');
        $stredit = get_string('edit');
        $strdelete = get_string('delete');
        $strduplicate =  get_string('duplicate');

        $selectallnone = html_writer::checkbox(null, null, false, null, array('onclick' => 'select_allnone(\'filter\'&#44;this.checked)'));
        $multidelete = html_writer::tag('button', 
                                    $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'dataform')), 
                                    array('name' => 'multidelete',
                                            'onclick' => 'bulk_action(\'filter\'&#44; \''. htmlspecialchars_decode(new moodle_url($filterbaseurl, $linkparams)). '\'&#44; \'delete\')'));
    

        $table = new html_table();
        $table->head = array($strfilters, $strdescription, $strperpage, 
                            $strcustomsort, $strcustomsearch, $strvisible, 
                            $stredit, $strduplicate, $multidelete, $selectallnone);
        $table->align = array('left', 'left', 'center', 'left', 'left', 'center', 'center', 'center', 'center', 'center');
        $table->wrap = array(false, false, false, false, false, false, false, false, false, false);
        $table->attributes['align'] = 'center';
        
        foreach ($filters as $filterid => $filter) {
            $filtername = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('fedit' => $filterid, 'fid' => $filterid)), $filter->name);
            $filterdescription = shorten_text($filter->description, 30);
            $filteredit = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('fedit' => $filterid, 'fid' => $filterid)),
                            $OUTPUT->pix_icon('t/edit', $stredit));
            $filterduplicate = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('duplicate' => $filterid)),
                            $OUTPUT->pix_icon('t/copy', $strduplicate));
            $filterdelete = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('delete' => $filterid)),
                            $OUTPUT->pix_icon('t/delete', $strdelete));
            $filterselector = html_writer::checkbox("filterselector", $filterid, false);

            // visible
            if ($filter->visible) {
                $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
                $visible = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('hide' => $filterid)), $visibleicon);
            } else {
                $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
                $visible = html_writer::link(new moodle_url($filterbaseurl, $linkparams + array('show' => $filterid)), $visibleicon);
            }

            $sortoptions = '---';
            $searchoptions = $filter->search ? $filter->search : '---';
            
            // parse custom settings
            if ($filter->customsort or $filter->customsearch) {
                // parse filter sort settings
                $sortfields = array();
                if ($filter->customsort) {
                    $sortfields = unserialize($filter->customsort);
                }
                
                // parse filter search settings
                $searchfields = array();
                if ($filter->customsearch) {
                    $searchfields = unserialize($filter->customsearch);
                }

                // get fields objects
                $fields = $df->get_fields();
                
                if ($sortfields) {
                    foreach ($sortfields as $sortieid => $sortdir) {
                        // check if field participates in default sort
                        $strsortdir = $sortdir ? 'Descending' : 'Ascending';
                        $sortoptions = $OUTPUT->pix_icon('t/'. ($sortdir ? 'down' : 'up'), $strsortdir);
                        $sortoptions .= ' '. $fields[$sortieid]->field->name. '<br />';
                    }
                }
            
                if ($searchfields) {
                    $searcharr = array();
                    foreach ($searchfields as $fieldid => $searchfield) {
                        $fieldoptions = array();
                        if (isset($searchfield['AND']) and $searchfield['AND']) {
                            //$andoptions = array_map("$fields[$fieldid]->format_search_value", $searchfield['AND']);
                            $options = array();
                            foreach ($searchfield['AND'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = 'AND <b>'. $fields[$fieldid]->field->name. '</b>:'. implode(',', $options);
                        }
                        if (isset($searchfield['OR']) and $searchfield['OR']) {
                            //$oroptions = array_map("$fields[$fieldid]->format_search_value", $searchfield['OR']);
                            $options = array();
                            foreach ($searchfield['OR'] as $option) {
                                if ($option) {
                                    $options[] = $fields[$fieldid]->format_search_value($option);
                                }
                            }
                            $fieldoptions[] = 'OR <b>'. $fields[$fieldid]->field->name. '</b>:'. implode(',', $options);
                        }
                        if ($fieldoptions) {
                            $searcharr[] = implode('<br />', $fieldoptions);
                        }
                    }
                    if ($searcharr) {
                        $searchoptions = implode('<br />', $searcharr);
                    }
                }
            }

            $table->data[] = array(
                $filtername,
                $filterdescription,
                $filter->perpage,
                $sortoptions,
                $searchoptions,
                $visible,
                $filteredit,
                $filterduplicate,
                $filterdelete,
                $filterselector
            );
        }
                 
        echo html_writer::table($table);
    }
}

$df->print_footer();