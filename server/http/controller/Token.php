<?php

namespace server\http\controller;

use common\model\UserModel;
use App;

class Token extends Controller
{
    public function view()
    {
        return false;
    }

    public function update()
    {
        if (!isset($this->request->post['token'])) {
            $username = $this->request->post['username'];
            $password = $this->request->post['password'];
            $user = UserModel::findOne(['username' => $username, "password" => md5($password)]);
            if ($user) {

                $redis = new \Redis();
                $redis->connect(REDIS_HOST,REDIS_PORT);

                // 如果该用户已经登陆在线,获取fd加入待关闭的队列中
                if($redis->sIsMember("onlineList",$user['id'])){
                    $fd  = $redis->hGet("userId:userFd" , $user['id'] );
                    $redis->lPush("closeQueue",$fd);
                }
                $redis->close();
                $token = md5(time() + rand(1000, 9999));
                UserModel::update(['access_token' => $token], ['id' => $user['id']]);
                return ['status' => 1, "token" => $token, "user" => $user];
            } else {
                return ['status' => 0];
            }
        } else {
            $token = $this->request->post['token'];
            $user = UserModel::findOne(['access_token' => $token]);
            if ($user) {
                return ['status' => 1, "token" => $token, "user" => $user];
            } else {
                return ['status' => 0];
            }
        }
    }
}