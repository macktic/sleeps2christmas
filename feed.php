<?php
header('Content-Type: application/json');

$sleeps = file_get_contents('https://sleeps2christmas.sebs.space/index.php?tdn=Santa');


$data = array('updateDate' => gmdate('Y-m-d\TH:i:s\Z', time() - 300),
    'uid' => 'urn:uuid:' . sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff), random_int(0x8000, 0xbfff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)),
    'titleText' => 'Sleeps 2 Santa',
    'redirectionUrl' => 'https://sleeps2christmas.sebs.space/',
    'mainText' => $sleeps );

echo json_encode([$data]);
?>
