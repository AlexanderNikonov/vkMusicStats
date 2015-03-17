<?php

/**
 * @name musicStats.php
 * @author Alexander Nikonov
 * @version 0.1a
 * @license  "THE BEER-WARE LICENSE":
 * Can do whatever you want with this stuff. 
 * If we meet some day, and you think
 * this stuff is worth it, you can buy me a beer.
 * 
 */
@ini_set('error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE);

//display_errors(true);
class VkMusicStats {

    protected $id = 1;
    protected $userFields = 'sex,bdate,city';
    static $apiURL = 'https://api.vk.com/method/';
    protected $methods = [
        'getMembers' => 'groups.getMembers',
        'getUser' => 'users.get',
        'getAudio' => 'audio.get',
        'getCities' => 'database.getCitiesById',
    ];
    protected $mysql;
    protected $url;
    protected $showLog = TRUE;
    protected $accessToken = 'f577c6e8d3a2501d3fa39c11e7533d885aca05092d9bbe7866cdbf3a7a26ba140e4a52ecce9613ad'; //Тут нужно вписать токен, полученный от VK
    protected $ruCaptchaApiKey = '38a064dd87b341f32cd859b44c4'; // https://rucaptcha.com

    /**
     * 
     * @param int $id
     * @param int $type
     * @return boolean
     * @throws \Exception
     */

    public function __construct() {
        $dsn = "mysql:host=localhost;dbname=vk;charset=utf8";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];
        $this->mysql = new \PDO($dsn, 'vk', 'vk', $opt);
        $this->mysql->exec("SET NAMES 'utf8';");
        return true;
    }

    /**
     * 
     * @param array $array
     * @param string $method
     * @return boolean
     * @throws \Exception
     */
    private function buildUrl(array $array, $method = 'getPosts') {
        if (!isset($this->methods[$method])) {
            throw new \Exception('Unknown method');
        }
        if (is_array($array)) {
            if (count($array) === 0) {
                throw new \Exception('The array can not be empty');
            }
        } else {
            throw new \Exception('Requires an array of data');
        }
        $this->url = self::$apiURL . $this->methods[$method] . '?access_token=' . $this->accessToken . '&' . http_build_query($array);
        return true;
    }

    /**
     * 
     * @param type $response
     */
    private function _addUser($response) {
        $STH = $this->mysql->prepare("INSERT INTO  `users` (
                                        `user_id` ,`first_name`, `last_name`, `sex`,`bdate`,`city`, `hidden`
                                    ) VALUES (
                                        :user_id , :first_name, :last_name, :sex, :bdate, :city, :hidden
                                    );");
        $hidden = $response->hidden ? 1 : 0;
        $STH->bindValue(':user_id', $response->uid, PDO::PARAM_INT);
        $STH->bindValue(':first_name', $response->first_name, PDO::PARAM_STR);
        $STH->bindValue(':last_name', $response->last_name, PDO::PARAM_STR);
        $STH->bindValue(':sex', $response->sex, PDO::PARAM_INT);
        $STH->bindValue(':bdate', $response->bdate ? $response->bdate : '', PDO::PARAM_STR);
        $STH->bindValue(':city', $response->city ? $response->city : 0, PDO::PARAM_INT);
        $STH->bindValue(':hidden', $hidden, PDO::PARAM_INT);
        $STH->execute();
        if ($this->showLog) {
            echo "Add user $id DONE!" . PHP_EOL;
        }
    }

    /**
     * 
     * @param int $id
     * @return boolean
     */
    private function checkUser($id = 0, array $userList = []) {
        if (count($userList) > 0) {
            foreach ($userList as $key => $value) {

                $userId = $value->uid;

                $stm = $this->mysql->prepare('SELECT * FROM `users` WHERE `user_id`=' . $userId);

                $stm->execute();
                if ($stm->fetchColumn() === FALSE && $stm->fetchColumn() !== 0) {
                    $this->_addUser($value);
                }
            }
            return true;
        } else {
            echo 'blas';
        }
        if ($id == 0) {
            return true;
        }
        $id = intval($id);
        $stm = $this->mysql->prepare('SELECT * FROM `users` WHERE `user_id`=' . $id);
        $stm->execute();
        if ($stm->fetchColumn() === FALSE && $stm->fetchColumn() !== 0) {
            $this->buildUrl([
                'user_ids' => $id,
                'fields' => $this->userFields,
                    ], 'getUser');
            $data = json_decode(file_get_contents($this->url));
            $response = $data->response[0];
            $this->_addUser($response);
        }
        return true;
    }

    /**
     * 
     * @param type $id
     */
    public function getMembers($id = 31272583) {
        $this->buildUrl([
            'group_id' => $id,
            'fields' => $this->userFields,
                ], 'getMembers');
        $data = json_decode(file_get_contents($this->url));
        $response = $data->response;
        $count = $response->count;
        $userList = [];

        $userList[] = $response->users;
        for ($i = 0; $i < $count; $i = $i + 1000) {
            $this->buildUrl([
                'group_id' => $id,
                'fields' => $this->userFields,
                'offset' => $i,
                    ], 'getMembers');
            $data = json_decode(file_get_contents($this->url));
            $userList[] = $data->response->users;
        }
        foreach ($userList as $key => $value) {
            $this->checkUser(0, $value);
        }
        return true;
    }

    /**
     * 
     * @param type $last
     * @param type $captcha_sid
     * @param type $captcha_key
     * @return boolean
     */
    public function getAudio($last = 0, $captcha_sid = 0, $captcha_key = '') {
        $query = "SELECT * FROM users";

        $statement = $this->mysql->prepare($query);
        if (!$statement->execute($parameters))
            return false;
        $ii = 0;
        while ($row = $statement->fetch(PDO::FETCH_OBJ)) {
            $ii++;
            if ($row->user_id < $last) {
                continue;
            }
            $this->buildUrl([
                'owner_id' => $row->user_id,
                'captcha_sid' => $captcha_sid,
                'captcha_key' => $captcha_key,
                    ], 'getAudio');
            $data = json_decode(file_get_contents($this->url));
            if ($data->error->error_code == 201 || $data->error->error_code == 15) {
                continue;
            } elseif (!isset($data->error)) {
                $resp = $data->response;
                foreach ($resp as $key => $value) {
                    if ($key == 0) {
                        continue;
                    }
                    $stm = $this->mysql->prepare('SELECT * FROM `audio` WHERE `aid`=' . $value->aid);
                    $stm->execute();
                    if ($stm->fetchColumn() === FALSE && $stm->fetchColumn() !== 0) {
                        $STH = $this->mysql->prepare("INSERT INTO  `audio` (
                                        `aid` ,`artist`, `title`, `genre`
                                    ) VALUES (
                                        :aid , :artist, :title, :genre
                                    );");
                        $genre = $value->genre ? $value->genre : 0;
                        $STH->bindValue(':aid', $value->aid, PDO::PARAM_INT);
                        $STH->bindValue(':artist', $value->artist, PDO::PARAM_STR);
                        $STH->bindValue(':title', $value->title, PDO::PARAM_STR);
                        $STH->bindValue(':genre', $genre, PDO::PARAM_INT);
                        $STH->execute();
                    }
                    $STH = $this->mysql->prepare("INSERT INTO  `users_audio` (
                                       `uid`, `aid`
                                    ) VALUES (
                                        :uid , :aid
                                    );");
                    $STH->bindValue(':aid', $value->aid, PDO::PARAM_INT);
                    $STH->bindValue(':uid', $row->user_id, PDO::PARAM_INT);
                    $STH->execute();
                }
            } else {
                if ($data->error->error_code == 14) {
                    echo 'err captcha';
                    //   echo $data->error->captcha_sid . " - $row->user_id - " . ',<img src="' . $data->error->captcha_img . '"/> -- ' . $ii;
                    $captcha = file_get_contents($data->error->captcha_img);
                    $name = 'upl/' . time() . '_captcha.png';
                    if (!file_put_contents($name, $captcha)) {

                        exit('upload captcha error');
                    }
                    $text = $this->recognize($name, $this->ruCaptchaApiKey, true, "rucaptcha.com");
                    if ($text) {
                        //  $this->postCaptcha($data->error->captcha_sid, $text);
                        $this->getAudio($row->user_id, $data->error->captcha_sid, $text);
                    } else {
                        var_dump($text);
                    }
                } else if ($data->error->error_code == 6) {
                    echo "Too many requests per second. Sleep 5 seconds" . PHP_EOL;
                    sleep(3);

                    $this->getAudio($row->user_id);
                } else {
                    var_dump($data, $row->user_id, $ii);
                    exit();
                }
            }
            echo $row->user_id . ' OK-' . $ii . PHP_EOL;
        }
    }

    /**
     * 
     * @param type $filename
     * @param type $apikey
     * @param type $is_verbose
     * @param type $domain
     * @param type $rtimeout
     * @param type $mtimeout
     * @param type $is_phrase
     * @param type $is_regsense
     * @param type $is_numeric
     * @param type $min_len
     * @param type $max_len
     * @param type $language
     * @return boolean
     */
    protected function recognize(
    $filename, $apikey, $is_verbose = true, $domain = "rucaptcha.com", $rtimeout = 5, $mtimeout = 120, $is_phrase = 0, $is_regsense = 0, $is_numeric = 0, $min_len = 0, $max_len = 0, $language = 0
    ) {
        if (!file_exists($filename)) {
            if ($is_verbose)
                echo "file $filename not found\n";
            return false;
        }
        $postdata = array(
            'method' => 'post',
            'key' => $apikey,
            'body' => base64_encode(file_get_contents($filename)),
            'phrase' => $is_phrase,
            'regsense' => $is_regsense,
            'numeric' => $is_numeric,
            'min_len' => $min_len,
            'max_len' => $max_len,
            'language' => $language,
            'method' => 'base64',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://$domain/in.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            if ($is_verbose)
                echo "CURL returned error: " . curl_error($ch) . "\n";
            return false;
        }
        curl_close($ch);
        if (strpos($result, "ERROR") !== false) {
            if ($is_verbose)
                echo "server returned error: $result\n";
            return false;
        }
        else {
            $ex = explode("|", $result);
            $captcha_id = $ex[1];
            if ($is_verbose)
                echo "captcha sent, got captcha ID $captcha_id\n";
            $waittime = 0;
            if ($is_verbose)
                echo "waiting for $rtimeout seconds\n";
            sleep($rtimeout);
            while (true) {
                $result = file_get_contents("http://$domain/res.php?key=" . $apikey . '&action=get&id=' . $captcha_id);
                if (strpos($result, 'ERROR') !== false) {
                    if ($is_verbose)
                        echo "server returned error: $result\n";
                    return false;
                }
                if ($result == "CAPCHA_NOT_READY") {
                    if ($is_verbose)
                        echo "captcha is not ready yet\n";
                    $waittime += $rtimeout;
                    if ($waittime > $mtimeout) {
                        if ($is_verbose)
                            echo "timelimit ($mtimeout) hit\n";
                        break;
                    }
                    if ($is_verbose)
                        echo "waiting for $rtimeout seconds\n";
                    sleep($rtimeout);
                }
                else {
                    $ex = explode('|', $result);
                    if (trim($ex[0]) == 'OK')
                        return trim($ex[1]);
                }
            }

            return false;
        }
    }

    /**
     * 
     * @param type $captcha_sid
     * @param type $captcha_key
     * @return boolean
     */
    public function postCaptcha($captcha_sid, $captcha_key) {
        $this->buildUrl([
            'user_ids' => 1,
            'fields' => $this->userFields,
            'captcha_sid' => $captcha_sid,
            'captcha_key' => $captcha_key,
                ], 'getUser');
        $data = json_decode(file_get_contents($this->url));
        return true;
    }

    /**
     * 
     * @param type $param
     */
    public function getCityName($param) {
        $this->buildUrl([
            'city_ids' => $param,
                ], 'getCities');
        $data = json_decode(file_get_contents($this->url));
        var_dump($data);
    }

}

$ex = new VkMusicStats;
//$ex->getMembers();
//$ex->postCaptcha(322083288459, 'hhxa2');
//$ex->getAudio(108619124);
//$ex->getCityName('21611,0,1,1769,625');
