<?php

include('../config.php');
include(STORE_DIR.'/config/config.php');
include('../include/my_functions.php');
include('../include/common.php');
include('../include/openssl_functions.php');

$stage     = gpvar('stage');
$serial    = gpvar('serial');
$sortfield = gpvar('sortfield');
$ascdec    = gpvar('ascdec');
$passwd    = gpvar('passwd');
$submit    = gpvar('submit');

$search       = gpvar('search');
$show_valid   = gpvar('show_valid');
$show_revoked = gpvar('show_revoked');
$show_expired = gpvar('show_expired');


# Prevent handling certs that don't belong to user
if ($serial && CAdb_issuer($serial) != $PHPki_user && $PHPki_user != $PHPki_admin) { 
	$stage = 'goaway';
}

if ( !($show_valid.$show_revoked.$show_expired) ) {
	$show_valid   = 'V';
	$show_revoked = 'R';
	$show_expired = 'E';
}

$qstr_filter =	'search='.htvar($search).'&'.
		"show_valid=$show_valid&".
		"show_revoked=$show_revoked&".
		"show_expired=$show_expired&";

$qstr_sort   = "sortfield=$sortfield&ascdec=$ascdec";

switch ($stage) {
case 'goaway':
	printHeader(false);
	?> <p><center><h1><font color=red>YOU ARE A VERY BAD BOY!</font></h2><?
	break;

case 'display':
	printHeader(false);

	?>
       	<center><h2><font face="Helvetica, Arial, sans-serif">Certificate Details</font></h2>
       	</center>
	<center><font color=#0000AA><h3>(#<?=$serial?>)<br><?=htvar(CA_cert_cname($serial).' <'.CA_cert_email($serial).'>')?> </h3></font></center>
	<?

	if ($revoke_date = CAdb_is_revoked($serial))
		print '<center><font color=red><h2>REVOKED '.$revoke_date.'</h2></font></center>';

       	print '<pre>'.CA_cert_text($serial).'</pre>';
	break;

case 'dl-confirm':
	printHeader('ca');

	$rec = CAdb_get_entry($serial);

	?>
	<h3><br /><font face="Arial, Helvetica, sans-serif">You are about to download the <font color=red>PRIVATE</font> certificate key for 
    <?=$rec['common_name'].' &lt;'.$rec['email'].'&gt; '?>
	</font></h3>
	<h3><font color=red face="Arial, Helvetica, sans-serif">DO NOT DISTRIBUTE THIS FILE TO THE PUBLIC!</font></h3>
	<form action="<?=$PHP_SELF.'?stage=download&serial='.$serial.'&'.$qstr_sort.'&'.$qstr_filter?>" method=post>
	<strong><font face="Arial, Helvetica, sans-serif">Download: </font></strong>
	<font face="Arial, Helvetica, sans-serif">
	<input type=submit name=submit value="PKCS#12">
	<input type=submit name=submit value="PEM">
&nbsp; or </font>&nbsp;
	<input type=submit name=submit value="Go Back">
	</form>
	<?

	break;

case 'download':
	$rec = CAdb_get_entry($serial);

	switch ($submit) {
	case 'PKCS#12':
		upload("$config[pfx_dir]/$serial.pfx", "$rec[common_name] <$rec[email]>.p12", 'application/x-pkcs12');
		break;
	case 'PEM':
		upload(array("$config[private_dir]/$serial-key.pem","$config[new_certs_dir]/$serial.pem",$config['cacert_pem']), "$rec[common_name] <$rec[email]>.pem",'application/pkix-cert');
		break;
			
	default:
		header("Location: ${PHP_SELF}?$qstr_sort&$qstr_filter");
	}

	break;

case 'revoke-form':
	$rec = CAdb_get_entry($serial);

	printHeader('ca');

	?>
	<h4>
	<font face="Arial, Helvetica, sans-serif">You are about to <font color=red>REVOKE</font> the following certificate:
	</hr>
</font>
<table width=500><tr>
       	<td width=25% style='white-space: nowrap'>
       	<p align=right>
	    <font face="Arial, Helvetica, sans-serif">Serial Number<br>
       	User's Name<br>
       	Email Address<br>
       	Organization<br>
       	Department/Unit<br>
       	Locality<br>
       	State/Province<br>
       	Country</font><br>
       	</td>
	<?

	print '
       	<td>
	'.htvar($rec[serial]).'<br>
       	'.htvar($rec[common_name]).'<br>
       	'.htvar($rec[email]).'<br>
       	'.htvar($rec[organization]).'<br>
       	'.htvar($rec[unit]).'<br>
       	'.htvar($rec[locality]).'<br>
       	'.htvar($rec[province]).'<br>
       	'.htvar($rec[country]).'<br>
       	</td>
       	</tr></table>
	<h4>Are you sure?</h4>
       	<p><form action="'.$PHP_SELF.'?'.$qstr_sort.'&'.$qstr_filter.'" method=post>
	<input type=hidden name=stage value=revoke >
	<input type=hidden name=serial value='.$serial.' >
       	<input type=submit name=submit value=Yes >&nbsp
       	<input type=submit name=submit value=Cancel>
       	</form>';
	
	break;

case 'revoke':
	$ret = true;
	if ($submit == 'Yes') 
		list($ret, $errtxt) = CA_revoke_cert($serial);

	if (! $ret) {
		printHeader('ca');

		print "<form action=\"$PHP_SELF?stage=revoke-form&serial=$serial&$qstr_sort&$qstr_filter\" method=post>";
		?>
		<font color=#ff0000>  </font>
  <h2><br /><font color="#ff0000" face="Arial, Helvetica, sans-serif">There was an error revoking your certificate
  .</font></h2>
  <br>
		<blockquote>
		<h3><font face="Arial, Helvetica, sans-serif">Debug Info:</font></h3>
		<pre><?=$errtxt?></pre>
		</blockquote>
<p>
		<input type=submit name=submit value=Back>
		<p>
		</form>
		<?
	}
	else
		header("Location: ${PHP_SELF}?$qstr_sort&$qstr_filter");
	break;

case 'renew-form':
	# 
	# Get last known values submitted by this user.  We only really
	# need the expiry value, but the old cert values will override
	# the rest.
	#
	if (! $submit and file_exists("config/user-${PHPki_user}.php"))
		include("config/user-${PHPki_user}.php");

	# 
	# Get values from the old certificate.
	#
	$rec = CAdb_get_entry($serial);
	$country      = $rec['country'];
	$province     = $rec['province'];
	$locality     = $rec['locality'];
	$organization = $rec['organization'];
	$unit         = $rec['unit'];
	$common_name  = $rec['common_name'];
	$email        = $rec['email'];

	printHeader('ca');
	?>
	<body onLoad="self.focus();document.form.passwd.focus()">
	<form action="<?=$PHP_SELF.'?'.$qstr_sort.'&'.$qstr_filter?>" method=post name=form>
	<table width=99%>
	<th colspan=2><h5 align="center"><font face="Arial, Helvetica, sans-serif">Certificate Renewal Form</font></h5></th>

	<tr>
	<td width=25%>Common Name </td>
	<td><input type=text name=common_name value="<?= htvar($common_name)?>" size=50 maxlength=60 disabled></td>
	</tr>

	<tr>
	<td>E-mail Address </td>
	<td><input type=text name=email value="<?=htvar($email)?>" size=50 maxlength=60 disabled></td>
	</tr>

	<tr>
	<td>Organization </td>
	<td><input type=text name=organization value="<?=htvar($organization)?>" size=40 maxlength=60 disabled></td>
	</tr>

	<tr>
	<td>Department/Unit </td><td><input type=text name=unit value="<?= htvar($unit) ?>" size=40 maxlength=60 disabled></td>
	</tr>

	<tr>
	<td>Locality</td><td><input type=text name=locality value="<?= htvar($locality) ?>" size=30 maxlength=30 disabled></td>
	</tr>

	<tr>
	<td>State/Province</td><td><input type=text name=province value="<?= htvar($province) ?>" size=30 maxlength=30 disabled></td>
	</tr>

	<tr>
	<td>Country</td>
	<td><input type=text name=country value="<?= htvar($country) ?>" size=2 maxlength=2 disabled></td>
	</tr>

	<tr>
	<td>Certificate Password </td>
	<td><input type=password name=passwd value="<?= htvar($passwd) ?>" size=15 maxlength=15></td>
	</tr>

	<tr>
	<td>Certificate Life </td>
	<td><select name=expiry>
	<?

	print "<option value=1 " . ($expiry == 1 ? "selected='selected'" : "") . " >1 Year</option>\n" ;
	for ( $i = 2 ; $i < 6 ; $i++ ) {
		print "<option value=$i " . ($expiry == $i ? "selected='selected'" : "") . " >$i Years</option>\n" ;
	}

	?>

	</select></td>
	</tr>
	<tr>
	<td>
	<center><input type=submit name=submit value="Submit Request">&nbsp
	<input type=submit name=submit value="Back"></center>	</td>
	<td>
	<input type=hidden name=stage value=renew>
	<input type=hidden name=serial value=<?=$serial?>>	</td>
	</tr>
	</table>
	</form>
	<?

	printFooter();
	break;

case 'renew':
	$ret = true;
	if ($submit == "Submit Request")
		list($ret, $errtxt) = CA_renew_cert($serial, $expiry, $passwd);

	if (! $ret) {
		printHeader('ca');

		print "<form action=\"$PHP_SELF?stage=renew-form&serial=$serial&$qstr_sort&$qstr_filter\" method=post>";
		?>
  
  <h2><br /><font color="#ff0000" face="Verdana, Arial, Helvetica, sans-serif">There was an error creating your certificate.</font></h2>
  <br>
		<blockquote>
		<h3><font face="Verdana, Arial, Helvetica, sans-serif">Debug Info:</font></h3>
		<pre><?=$errtxt?></pre>
		</blockquote>
		<p>
		<input type=submit name=submit value=Back>
		<p>
		</form>
		<?
	}
	else {
		header("Location: $PHP_SELF?$qstr_sort&$qstr_filter");
	}

	break;

default:

	printHeader('ca');

	?>
	<body onLoad="self.focus();document.filter.search.focus()">
	<table>
	<br />
	<br />
	<br />
	<tr><td class="tdpki" colspan=8><center>
	<form action="<?="$PHP_SELF?$qstr_sort"?>" method=get name=filter>
        Search: <input type=text name=search value="<?=htvar($search)?>" style="font-size: 11px;" maxlength=60 size=30>
        &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp<input type=checkbox name=show_valid value="V" <?=($show_valid?'checked'
:'')?>>Valid
        &nbsp&nbsp<input type=checkbox name=show_revoked value="R" <?=($show_revoked?'checked':'')?>>Revoked
        &nbsp&nbsp<input type=checkbox name=show_expired value="E" <?=($show_expired?'checked':'')?>>Expired
        &nbsp&nbsp&nbsp&nbsp&nbsp<input type=submit name=submit value="Apply Filter" style="font-size: 11px;">
        </form>
	</center></td>
	</tr>
	<?

	if (! $sortfield) {
		$sortfield = 'email' ;
		$ascdec = 'A';
	}

	if ($ascdec == 'A') {
		$arrow_gif = '../images/uparrow-blue.gif';
		$ht_ascdec = 'D';
	}
	else {
		$arrow_gif = '../images/downarrow-blue.gif';
		$ht_ascdec = 'A';
	}

	print '<tr>';
	$headings = array(
		status=>"Status", issued=>"Issued", expires=>"Expires",
		common_name=>"User's Name", email=>"E-mail", 
		organization=>"Organization", unit=>"Department", 
		locality=>"Locality"
	);

	foreach($headings as $field=>$head) {
		print '<th><font face=Helvetica,arial size=2> <a href="'.$PHP_SELF.'?sortfield='.$field.'&ascdec=A&'.$qstr_filter.'" title="Click to sort on this column."><u>'.$head.'</u></a></font>';

		if ($sortfield == $field) {
			print	'&nbsp<a href="'.$PHP_SELF.'?sortfield='.$field.'&ascdec='.$ht_ascdec.'&'.$qstr_filter.'" >'.
				'<img src='.$arrow_gif.' height=12 alt=\'Change sort order.\' title=\'Click to reverse sort order.\' border=\'0\'></a>';
		}

		print '</th>';
	}
	print '</tr>';

	$x = "^[$show_valid$show_revoked$show_expired]";

	if ($PHPki_user == $PHPki_admin) {
		$x = "$x.*$search";
	}
	else {
		$x = "$x.*$search.*$PHPki_user|$x.*$PHPki_user.*$search";
	}

	$db = csort(CAdb_to_array($x), $sortfield, ($ascdec=='A'?SORT_ASC:SORT_DESC));

	$stcolor = array(Valid=>'green',Revoked=>'red',Expired=>'orange');

	foreach($db as $rec) {
		print	'<tr style="font-size: 11px;">
			 <td><font color='.$stcolor[$rec['status']].'><b>' .$rec[status].'</b></font></td>
			 <td style="white-space: nowrap">'.$rec[issued].'</td>
			 <td style="white-space: nowrap">'.$rec[expires].'</td>
			 <td>'.$rec[common_name].'</td>
			 <td style="white-space: nowrap"><a href="mailto:' . htvar($rec['common_name']) . ' <' . htvar($rec['email']) . '>" >' . htvar($rec['email']) . '</a></td>
			 <td>'.htvar($rec[organization]).'</td>
			 <td>'.htvar($rec[unit]).'</td>
			 <td>'.htvar($rec[locality]).'</td>
			 <td><a href="'.$PHP_SELF.'?stage=display&serial='.$rec[serial].'" target=_certdisp>'.
			 '<img src=../images/display.png alt="Display" title="Display complete certificate details." border="0"></a>';

		if ($rec['status'] == 'Valid') {
			print '
			<a href="'.$PHP_SELF.'?stage=dl-confirm&serial='.$rec[serial].'&'.$qstr_sort.'&'.$qstr_filter.'">'.
			'<img src=../images/download-pri.png alt="Download" title="Download the PRIVATE certificate (PUBLIC KEY and CA ROOT CERT included). DO NOT DISTRIBUTE THIS TO THE PUBLIC!" border="0"></a>
			<a href="../search.php?stage=download&serial='.$rec[serial].'">'.
			'<img src=../images/download.png alt="Download" title="Download the user certificate (PUBLIC KEY included)" border="0"></a>
			<a href="'.$PHP_SELF.'?stage=revoke-form&serial='.$rec[serial].'&'.$qstr_sort.'&'.$qstr_filter.'">'.
			'<img src=../images/revoke.png alt="Revoke" title="Revoke the certificate when the e-mail address is no longer valid or the certificate password or private key has been compromised." border="0"></a>';
		}
		print '
		<a href="'.$PHP_SELF.'?stage=renew-form&serial='.$rec[serial].'&'.$qstr_sort.'&'.$qstr_filter.'">'.
		'<img src=../images/renew.png alt="Renew" title="Renew the certificate by revoking it, if necessary, and creating a replacement with a new expiration date." border="0"></a></td></tr>';
		
	}

	print '</table>';

	printFooter();
}
?>
