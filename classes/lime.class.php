<?php
class Lime extends Webpages {
    protected $proxy, $proxyauth, $u_lat, $u_long;
    public function __construct($proxy = null, $proxyauth = null)
    {
        $this->proxy = $proxy;
        $this->proxyauth = $proxyauth;
    }
    protected function connectWithLime($path, $token, $method = 'GET', $data = array())
    {
        $url = 'https://web-production.lime.bike/api/rider' . $path;
        return $this->Connect(array(
            'url' => $url,
            'header' => array('authorization:	Bearer ' . $token),
            'method' => $method,
            'proxy' => $this->proxy,
            'proxyauth' => $this->proxyauth
        ));
    }
    public function distanceBetweenCords($lat1, $lon1, $lat2, $lon2, $unit)
    {
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
    
    public function calculateDistance($bike)
    {
        $bike['distance'] = $this->distanceBetweenCords(
            $this->u_lat,
            $this->u_long,
            $bike['lat'],
            $bike['long'],
            'K'
        );
        return $bike;
    }
    public function filterClosest($bike)
    {
        return ($bike['distance'] <= 0.1);
    }
    private function filter($bike)
    {
        return array(
            'id' => $bike['id'],
            'number' => $bike['attributes']['plate_number'],
            'lat' => $bike['attributes']['latitude'],
            'long' => $bike['attributes']['longitude'],
            'last_activity_at' => $bike['attributes']['last_activity_at'],
        );
    }
    public function getMap($ne_lat, $ne_long, $sw_lat, $sw_long, $u_lat, $u_long, $token)
    {
        $this->u_lat = $u_lat;
        $this->u_long = $u_long;

        $map = $this->connectWithLime('/v1/views/map?ne_lat=' . $ne_lat . '&ne_lng=' . $ne_long . '&sw_lat=' . $sw_lat . '&sw_lng=' . $sw_long . '&user_latitude=' . $u_lat . '&user_longitude=' . $u_long . '&zoom=15.0', $token);
        if ($map['httpcode'] == 200) {
            $vehicles = json_decode($map['result'], true);
            $vehicles = $vehicles['data']['attributes']['bikes'];
            $vehicles = array_map(array($this, 'filter'), $vehicles);
            return array('success' => true, 'vehicles' => $vehicles);
        } else {
            return array(
                'success' => false,
                'error' => 'Can\'t get map. Httpcode: ' . $map['httpcode']
            );
        }
    }
    public function ring($id, $token)
    {
        $req = $this->connectWithLime('/v1/bikes/' . $id . '/ring', $token, 'POST');
        if ($req['httpcode'] != 200) {
            return array(
                'success' => false,
                'error' => 'Can\'t ring. Httpcode: ' . $req['httpcode']
            );
        }
        $call = json_decode($req['result'], true);
        if (count($call)===0) {
            return array('success' => true);
        } else {
            if (isset($call['bike_missing_report'])) {
                return $this->ring($id, $token);
            }
            $errors = array_column($call['errors'], 'status');
            return array(
                'success' => false,
                'error' => $errors,
                'httpcode' => $req['httpcode']
            );
        }
    }
}