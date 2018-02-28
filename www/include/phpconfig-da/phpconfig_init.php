<?PHP
/**
 *
 * Asterisk configuration file interface init file
 * we are using this init file to handle multiple installations
 * files.
 *
 *
 *
 *
 * phpconfig:,v 1.0 2003/07/03 17:19:37
 * Authors: Dave Packham <dave.packham@utah.edu>
 *          Rob Birkinshaw <robert.birkinshaw@utah.edu>
 */


// directories that contain your confiles
$conf_directories = array("../../../conf/dialup_admin/conf/");
//$conf_directories = array("../dialup_admin/conf/");

// temporary directory where conf file
// copies are placed
$temporary_directory = "/tmp";

// file prefix for temporary conf files
$temporary_file_prefix = "conf-";

// file that contains users in the form of "[username]"
// who have write privledges
$access_file = "";

// Since login screen is still in the works, fake
// the user has logged in with success for the prototype.
// for write access $fakeuser must exist in $access_file
$fakeuser = "";

// conf file directory displayed by default
$default_conf_file_direcotry = "../../../conf/dialup_admin/conf/";
//$default_conf_file_direcotry = "../dialup_admin/conf/";

// regular expression filter for valid conf files
//$conf_file_filter = "/.conf\$|.cnf\$|.attrs\$/";
$conf_file_filter = "/user_edit.attrs\$/";


// command for switch to read conf files
// commented out for demo
//$reset_cmd = "/bin/asterisk.reload";
//chaged for demo
$reset_cmd = "asterisk.reload";

// remark symbol in conf file
$remark = ";";


// HTML Output //
$images_dir = "images";
// page logo "top left"
$logo = "logo.gif";
// title for <head>
$title = "phpconfig for phpRADmin";
// title for page
$page_title = "phpconfig for phpRADmin";
// description for page <meta name="description">
$description = "RADIUS web management";
// keywords for page <meta name="keywords">
$keywords = "RADIUS";
// text to appear in footer bar
$footer_text = "Created by p0lar, Dave Packham & Rob Birkinshaw";
// link to web master
$webmaster = "http://www.asterisk.org";
// disclaimer
$disclaimer = "http://www.asterisk.org";
// link attached to logo
$logo_link = "http://www.asterisk.org";
// how many rows to set textarea control for
// editing and viewing conf files
$textarea_rows = 20;

?>