<?php
header('Content-Type: application/json');

$sleeps = file_get_contents('https://sleeps2christmas.sebs.space/index.php?tdn=Santa');


$data = array('updateDate' => date('c'),
    'uid' => uniqid(),
    'titleText' => 'Sleeps 2 Santa',
    'redirectinUrl' => 'https://sleeps2christmas.sebs.space/',
    'mainText' => $sleeps );

echo json_encode( $data );
?>
