<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Sleeps 2 Santa Help Page</title>
  </head>
  <body>
  <h2>Sleeps 2 Santa Help Page</h2>
  <?php
if (isset($_GET['error'])) { $error = $_GET['error']; } else { $error = "0"; }
if ($error != "0") {
  ?><P>You got to this page after an error.</p>
  <?php
  if ($error == "1" || $error == "3" || $error == "5" || $error == "7") {
    //error with timezone
    echo "<font color='red'>I didn't understand your timezone entry, please check below for correct syntax.<br/></font>";
  }
  if ($error == "2" || $error == "3" || $error == "6" || $error == "7") {
    //error with target date
    echo "<font color='red'>I didn't understand your target date entry, please check below for correct syntax.<br/></font>";
  }
  if ($error == "4" || $error == "5" || $error == "6" || $error == "7") {
    //unknown key given
    echo "<font color='red'>You entered invalid keys, please check below for options.<br/></font>";
  }
  if ($error == "8") {
    //unknown page
    echo "<font color='red'>You entered an invalid page, please check below for options.<br/></font>";
  }
}

include('vendor/erusev/parsedown/Parsedown.php');
$contents = file_get_contents('README.md');
$Parsedown = new Parsedown();
echo $Parsedown->text($contents);
   ?>

  </body>
</html>
