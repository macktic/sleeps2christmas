<?php
$targetday = "Christmas";
$error = "0";

foreach($_GET as $key => $value)
{
   if ($key == "tz") { $tz = $value; continue; }
   if ($key == "td") { $td = $value; continue; }
   if ($key == "tdn") { $tdn = $value; continue; }
   $error = "4";
   break;
}

function isValidTimezone($timezone) {
  return in_array($timezone, timezone_identifiers_list());
}

function stripQuotes($text) {
  return preg_replace('/^(\'(.*)\'|"(.*)")$/', '$2$3', $text);
}

if (isset($tz)) {
  $tz = stripQuotes($tz);
  if (isValidTimezone($tz)) {
    $tzone = $tz;
  } else {
    $tzone = "Europe/London";
    $error += 1;
  }
}
else {
    $tzone = "Europe/London";
}

$christmas = new DateTime(date('Y-12-25'), new DateTimeZone($tzone) );

if (isset($td)) {
  $date = date_parse($td);
  if ($date["error_count"] == 0 && checkdate($date["month"], $date["day"], $date["year"])) {
      $targdate = $td;
} else {
      // invalid date, using christmas.
      $targdate = $christmas;
      $error += 2;
  }
} else {
  $targdate = $christmas;
}

$targetdate = $targdate->format('Y-m-d');

$tdate = new DateTime("today", new DateTimeZone($tzone) );
$today = $tdate->format('Y-m-d');

if ($today > $targetdate) {
   $targetdate = date('Y-m-d', strtotime($targetdate . ' +1 year'));
}


if (isset($tdn)) {
  $tdn = stripQuotes($tdn);
  $targetday = strip_tags( trim($tdn));
}
if (isset($td)) {
  if (!isset($tdn)) {
    $tdn = "your date";
  }
}

$sleeps = floor((strtotime($targetdate) - strtotime($today))/60/60/24);
if ($error == "0") {
  if ($sleeps == "1") {
  echo "There is ". $sleeps." more sleep to ". $targetday.".";
} else {
  echo "There are ". $sleeps." sleeps to ". $targetday.".";
}

}

if ($error != "0") {
  ?>
  <!DOCTYPE html>
  <html lang="en" dir="ltr">
    <head>
      <meta charset="utf-8">
      <title>Sleeps 2 Santa, errors on page</title>
    </head>
    <body>
      <?php
       if ($sleeps == "1") {
         echo "There is ". $sleeps." more sleep to ". $targetday.".";
       } else {
         echo "There are ". $sleeps." sleeps to ". $targetday.".";
       }
       ?>
      <p><p>There were errors, <a href="error.php?error=<?php echo $error ?>">click here</a> to find out what went wrong.
    </body>
  </html>
<?php
}
 ?>
