<?php  // $Id: view.php,v 1.6 2006/04/05 13:59:50 thepurpleblob Exp $

    require_once("../../config.php");
    require_once("lib.php");
    require_once("locallib.php");

    $id = required_param('id',PARAM_INT);
    $action = optional_param('action','',PARAM_ALPHA);

    if (! $cm = get_record("course_modules", "id", $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }

    if (! $dialogue = get_record("dialogue", "id", $cm->instance)) {
        error("Course module is incorrect");
    }

    require_login($course->id, false, $cm);

    add_to_log($course->id, "dialogue", "view", "view.php?id=$cm->id", $dialogue->id, $cm->id);

    if (! $cw = get_record("course_sections", "id", $cm->section)) {
        error("Course module is incorrect");
    }

    // set up some general variables
    $usehtmleditor = can_use_html_editor();

    $strdialogue = get_string("modulename", "dialogue");
    $strdialogues = get_string("modulenameplural", "dialogue");

    print_header_simple("$dialogue->name", "",
                 "<a href=\"index.php?id=$course->id\">$strdialogues</a> -> $dialogue->name",
                 "", "", true,
                  update_module_button($cm->id, $course->id, $strdialogue), navmenu($course, $cm));

    // ...and if necessary set default action

    if (!isguest()) { // it's a teacher or student
        if (!$cm->visible and isstudent($course->id)) {
            $action = 'notavailable';
        }
        if (empty($action)) {
            $action = 'view';
        }
    }
    else { // it's a guest, oh no!
        $action = 'notavailable';
    }



/*********************** dialogue not available (for gusets mainly)***********************/
    if ($action == 'notavailable') {
        print_heading(get_string("notavailable", "dialogue"));
    }


    /************ view **************************************************/
    elseif ($action == 'view') {

        print_simple_box(format_text($dialogue->intro), 'center', '70%', '', 5, 'generalbox', 'intro');
        echo "<br />";
        // get some stats
        $countneedingrepliesself = dialogue_count_needing_replies_self($dialogue, $USER);
        $countneedingrepliesother = dialogue_count_needing_replies_other($dialogue, $USER);
        $countclosed = dialogue_count_closed($dialogue, $USER);

        // set the pane if it's in a GET or POST
        if (isset($_REQUEST['pane'])) {
            $pane = $_REQUEST['pane'];
        } else {
            // set default pane
            $pane = 0;
            if ($countneedingrepliesother) {
                $pane = 2;
           }
            if ($countneedingrepliesself) {
                $pane =1;
            }
        }

        // override pane setting if teacher has changed group
        if (isset($_GET['group'])) {
            $pane = 0;
        }

        // set up tab table
        $tabs->names[0] = get_string("pane0", "dialogue");
        if ($countneedingrepliesself == 1) {
            $tabs->names[1] = get_string("pane1one", "dialogue");
        } else {
            $tabs->names[1] = get_string("pane1", "dialogue", $countneedingrepliesself);
        }
        if ($countneedingrepliesother == 1) {
            $tabs->names[2] = get_string("pane2one", "dialogue");
        } else {
            $tabs->names[2] = get_string("pane2", "dialogue", $countneedingrepliesother);
        }
        if ($countclosed == 1) {
            $tabs->names[3] = get_string("pane3one", "dialogue");
        } else {
            $tabs->names[3] = get_string("pane3", "dialogue", $countclosed);
        }

        $tabs->urls[0] = "view.php?id=$cm->id&amp;pane=0";
        $tabs->urls[1] = "view.php?id=$cm->id&amp;pane=1";
        $tabs->urls[2] = "view.php?id=$cm->id&amp;pane=2";
        $tabs->urls[3] = "view.php?id=$cm->id&amp;pane=3";
        $tabs->highlight = $pane;
        dialogue_print_tabbed_heading($tabs);
        echo "<br />\n";


        switch ($pane) {
            case 0:
                if (isteacher($course->id)) {
                    /// Check to see if groups are being used in this dialogue
                    /// and if so, set $currentgroup to reflect the current group
                    $changegroup = isset($_GET['group']) ? $_GET['group'] : -1;  // Group change requested?
                    $groupmode = groupmode($course, $cm);   // Groups are being used?
                    $currentgroup = get_and_set_current_group($course, $groupmode, $changegroup);

                    /// Allow the teacher to change groups (for this session)
                    if ($groupmode) {
                        if ($groups = get_records_menu("groups", "courseid", $course->id, "name ASC", "id,name")) {
                            print_group_menu($groups, $groupmode, $currentgroup, "view.php?id=$cm->id");
                        }
                    }
                }

                if ($names = dialogue_get_available_users($dialogue)) {
                    print_simple_box_start("center");
                    echo "<form name=\"startform\" method=\"post\" action=\"dialogues.php\">\n";
                    echo "<input type=\"hidden\" name=\"id\"value=\"$cm->id\" />\n";
                    echo "<input type=\"hidden\" name=\"action\" value=\"openconversation\" />\n";
                    echo "<table align=\"center\" border=\"0\"><tr>\n";
                    echo "<td align=\"right\"><b>".get_string("openadialoguewith", "dialogue").
                        " : </b></td>\n";
                    echo "<td>";

                    choose_from_menu($names, "recipientid");
                    echo "</td></tr>\n";
                    echo "<tr><td align=\"right\"><b>".get_string("subject", "dialogue")." : </b></td>\n";
                    echo "<td><input type=\"text\" size=\"50\" maxsize=\"100\" name=\"subject\"
                        value=\"\" /></td></tr>\n";
                    echo "<tr><td colspan=\"2\" align=\"center\" valign=\"top\"><i>".
                        get_string("typefirstentry", "dialogue")."</i></td></tr>\n";
                    echo "<tr><td valign=\"top\" align=\"right\">\n";
                    helpbutton("writing", get_string("helpwriting"), "moodle", true, true);
                    echo "<br />";
                    echo "</td><td>\n";
                    print_textarea($usehtmleditor, 20, 75, 630, 300, "firstentry");
                    use_html_editor();
                    echo "</td></tr>";
                    echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"".
                        get_string("opendialogue","dialogue")."\"/></td></tr>\n";
                    echo "</table></form>\n";
                    print_simple_box_end();
                } else {
                    print_heading(get_string("noavailablepeople", "dialogue"));
                    print_continue("view.php?id=$cm->id");
                }
                break;
            case 1:
                // print active conversations requiring a reply
                dialogue_list_conversations_self($dialogue, $USER);
                break;
            case 2:
                // print active conversations requiring a reply from the other person.
                dialogue_list_conversations_other($dialogue, $USER);
                break;
            case 3:
                dialogue_list_conversations_closed($dialogue, $USER);
        }
    }

    /*************** no man's land **************************************/
    else {
        error("Fatal Error: Unknown Action: ".$action."\n");
    }

    print_footer($course);

?>
