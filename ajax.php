<?php
include 'includes/autoload.php';
include_once('/lib/webpages.php');
$webpages = new Webpages;
$data = json_decode(file_get_contents('php://input'), true);
header('Content-Type: application/json');

$lime = new lime();

$token = array(
    //oauth tokens
);
$tokenID = 0;
$proxy = 'no115.nordvpn.com:80';
$proxyauth = 'shirleyliu1313@gmail.com:yiyichi123';

function distance($lat1, $lon1, $lat2, $lon2, $unit) {
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return 0;
    }
    else {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);
    
        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }
}

if ($data['type'] == 'callClosest') {
    function calculateDistance($bike) {
        global $lat, $long;
        $bike['distance'] = distance($lat, $long, $bike['lat'], $bike['long'], 'K');
        return $bike;
    }
    function filterClosest($bike) {
        return ($bike['distance'] <= 0.2);
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
