<?php
include 'includes/autoload.php';
require (__DIR__ . '/../webpages/webpages.php');
$webpages = new Webpages;
header('Content-Type: application/json');

$proxy = null;
$proxyauth = null;
$lime = new lime($proxy, $proxyauth);

include 'tokens.php';
$tokenID = 0;

if (!isset($token) || count($token)<=0) {
    echo json_encode(array('success' => false, 'error' => 'Please edit tokens.php file'));
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if ($data['type'] == 'callClosest') {
    function calculateDistance($bike) {
        global $lat, $long, $lime;
        $bike['distance'] = $lime->distance($lat, $long, $bike['lat'], $bike['long'], 'K');
        return $bike;
    }
    function filterClosest($bike) {
        return ($bike['distance'] <= 0.1);
    }
    $lat = $data['lat'];
    $long = $data['long'];

    $vehicles = $lime->getMap($lat, $long, $lat, $long, $lat, $long, $token[$tokenID]);
    if (!$vehicles['success']) {
        echo json_encode(array('success' => false, 'error' => $vehicles['error']));
        exit;
    }

    $vehicles = array_map('calculateDistance', $vehicles['vehicles']);
    $vehicles = array_filter($vehicles, 'filterClosest');

    $count = array(
        'success' => 0,
        'error' => 0
    );
    $ids = [];

    for ($i = 0; $i < count($vehicles); $i++) {
        $call = $lime->ring($vehicles[$i]['id'], $token[$tokenID]);
        if ($call['success']) {
            $count['success']++;
            $ids[] = $vehicles[$i]['number'];
        } else {
            if (in_array('ring_bike_rate_limited', $call['error'])) {
                $tokenID++;
                if (!isset($token[$tokenID])) {
                    echo json_encode(array('success' => false, 'error' => 'No more accounts'));
                    exit;
                }
                $call = $lime->ring($vehicles[$i]['id'], $token[$tokenID]);
                if ($call['success']) {
                    $count['success']++;
                    $ids[] = $vehicles[$i]['number'];
                } else $count['error']++;
            } else {
                $count['error']++;
            }
        }
    }

    echo json_encode(array('success'=> true, 'result' => $count, 'ids' => $ids), JSON_PRETTY_PRINT);
}

?>
