<?php
class Lime {
    private $tokens, $tokenId = 0, $proxy, $proxyauth, $user_latitude, $user_longitude, $vehicles = [];

    public function __construct(array $tokens, string $proxy = null, string $proxyauth = null)
    {
        $this->tokens = $tokens;
        $this->proxy = $proxy;
        $this->proxyauth = $proxyauth;
    }
    private function connectWithLime($path, $method = 'GET', $data = [])
    {
        $url = 'https://web-production.lime.bike/api/rider' . $path;

        $headers = [
            'authorization:	Bearer ' . $this->tokens[$this->tokenId],
        ];
        $isPost = strtoupper($method) === 'POST';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, $isPost);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($isPost) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            if ($this->proxyauth) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyauth);
            }
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if  ($httpcode != 200) {
            throw new Exception('Cannot connect with lime service. Httpcode: ' . $httpcode);
        }
        return $output;
    }
    public function calculateDistance($lat2, $lon2)
    {
        $lat1 = $this->user_latitude;
        $lon1 = $this->user_longitude;
        $unit = 'K';

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
    public function filterClosest($distance)
    {
        $this->vehicles = array_filter($this->vehicles, function($bike) use ($distance) {
            return ($bike['distance'] <= $distance);
        });
        return $this;
    }
    public function updateMap($ne_lat, $ne_lng, $sw_lat, $sw_lng, $user_latitude, $user_longitude)
    {
        $this->user_latitude = $user_latitude;
        $this->user_longitude = $user_longitude;

        $data = compact('ne_lat', 'ne_lng', 'sw_lat', 'sw_lng', 'user_latitude', 'user_longitude');
        $data['zoom'] = '15.0';
        $query = http_build_query($data);
        $map = $this->connectWithLime('/v1/views/map?' . $query);
        $vehicles = json_decode($map, true);

        $vehicles = $vehicles['data']['attributes']['bikes'];
        $this->vehicles = array_map(function ($bike) {
            return [
                'id' => $bike['id'],
                'number' => $bike['attributes']['plate_number'],
                'lat' => $bike['attributes']['latitude'],
                'long' => $bike['attributes']['longitude'],
                'last_activity_at' => $bike['attributes']['last_activity_at'],
                'distance' => $this->calculateDistance(
                    $bike['attributes']['latitude'],
                    $bike['attributes']['longitude']
                ),
            ];
        }, $vehicles);
        return $this;
    }
    public function ring(string $id, $again = false)
    {
        $req = $this->connectWithLime('/v1/bikes/' . $id . '/ring', 'POST');
        $call = json_decode($req, true);
        if (count($call) === 0) {
            return ['success' => true];
        } else {
            if (isset($call['bike_missing_report']) && !$again) {
                return $this->ring($id, true);
            }
            $errors = array_column($call['errors'], 'status');
            if (in_array('ring_bike_rate_limited', $errors)) {
                $this->tokenId++;
                if (!isset($this->tokens[$this->tokenId])) {
                    return [
                        'success' => false,
                        'error' => 'No more accounts'
                    ];
                }
                return $this->ring($id);
            } else {
                return [
                    'success' => false,
                    'error' => $errors,
                ];
            }
        }
    }
    public function getVehicles()
    {
        return $this->vehicles;
    }
}