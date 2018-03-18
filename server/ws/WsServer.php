<?php

namespace server\ws;

use core\interfaces\ServerInterface;
use common\lib\MyRedis;
use Medoo\Medoo;
use Swoole\Http\Request;
use Swoole\Redis;
use Swoole\WebSocket\Server;
use core\App;
use common\lib\exception\FileNotExistException;

class WsServer implements ServerInterface
{
    public $config;

    /**
     * @return mixed|void
     * @throws FileNotExistException
     */
    public function run()
    {
        cli_set_process_title("WebSocket");
        $server = new Server(SERVER_HOST, WS_SERVER_PORT);
        $configFile = BASE_ROOT . "/server/ws/config/server.php";
        if (is_file($configFile)) {
            $this->config = require BASE_ROOT . "/server/ws/config/server.php";
        } else {
            throw new FileNotExistException("server config file");
        }
        $server->set($this->config);
        // 连接建立回调函数
        $server->on("open", function (Server $server, Request $request) {
            App::$comp->router->dispatch(['server' => $server, "request" => $request], "open");
        });
        // 接受消息回调函数
        $server->on("message", function (Server $server, $frame) {
            App::$comp->router->dispatch(['server' => $server, "frame" => $frame], "message");
        });
        // http接受请求回调函数
        $server->on("request", function (Server $server, $response) {
//           App::$comp->router->dispatch(['server' => $server, "response" => $response], "request");
        });
        // 连接关闭回调函数
        $server->on("close", function (Server $server, $fd, $reactorId) {
            App::$comp->router->dispatch(['server' => $server, "fd" => $fd, 'reactorId' => $reactorId], "close");
        });
        // 投递task回调函数
        $server->on("task", function (Server $server, int $taskId, int $workerId, $data) {
            App::$comp->router->dispatch(['server' => $server, 'taskId' => $taskId, 'workerId' => $workerId, 'data' => $data], 'task');

        });
        // task任务完成回调
        $server->on("finish", function (Server $server, int $taskId, string $data) {

        });
        // worker start 回调
        $server->on("WorkerStart", function (Server $server, int $workId) {
            // 每个worker各自拥有自己的redis/mysql 连接,在action中通过$this->server->db/redis调用
            $server->redis = App::createObject(MyRedis::class);
            $server->db = App::createObject(Medoo::class);
            // 设置用户重复登陆时自动断开发送消息通知，每个worker都有自己独立的定时器
            // 所以设置有多少个worker就会生成多少个定时器并发执行
            // 只有worker才设置定时器，taskWorker不设置
            if (!$server->taskworker) {
                $server->tick(500, function () use ($server) {
                    $closeFd = $server->redis->rPop("closeQueue");
                    if ($closeFd && $server->exist($closeFd)) {
                        //此处有可能消息没发送就关闭了连接
                        //todo
                        $server->push($closeFd, json_encode(['type' => 'repeat']));
                        $server->close($closeFd);
                    }
                });
            }
            if($workId == 1){
                $client = new Redis();
                var_dump($client);
                $client->on('message', function (Redis $client, $result) use ($server) {
                    var_dump($result);
                });
                $client->connect(REDIS_HOST, REDIS_PORT, function (Redis $client, $result) {
                    $client->subscribe('applyCH');
                    echo "redis链接成功";
                });

            }
        });
        $server->start();
    }
}