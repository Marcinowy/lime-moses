<?php
include 'includes/autoload.php';
header('Content-Type: application/json');

include 'tokens.php';

$proxy = null;
$proxyauth = null;

if (!isset($token) || count($token)<=0) {
    echo json_encode([
        'success' => false,
        'error' => 'Please edit tokens.php file',
    ]);
    exit;
}

$lime = new lime($token, $proxy, $proxyauth);

$data = json_decode(file_get_contents('php://input'), true);
if ($data['type'] == 'callClosest') {
    try {
        $vehicles = $lime->updateMap(
            $data['lat'],
            $data['long'],
            $data['lat'],
            $data['long'],
            $data['lat'],
            $data['long']
        )->filterClosest(0.1)->getVehicles();
        
        $count = [
            'success' => 0,
            'error' => 0
        ];
        $ids = [];

        for ($i = 0; $i < count($vehicles); $i++) {
            $call = $lime->ring($vehicles[$i]['id']);
    
            if ($call['success']) {
                $count['success']++;
                $ids[] = $vehicles[$i]['number'];
            } else {
                $count['error']++;
            }
        }

        echo json_encode([
            'success'=> true,
            'result' => $count,
            'ids' => $ids,
        ], JSON_PRETTY_PRINT);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e,
        ], JSON_PRETTY_PRINT);
    }
}
?>
