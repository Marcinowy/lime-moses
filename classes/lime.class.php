<?php
class Lime extends Webpages {
    protected $proxy, $proxyauth;
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
    private function filter($bike) {
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