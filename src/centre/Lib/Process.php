<?php
/**
 * Created by PhpStorm.
 * User: liuzhiming
 * Date: 16-8-22
 * Time: 下午6:04
 */

namespace Lib;


class Process
{
    private static $table;
    static private $column = [
        "taskId" => [\swoole_table::TYPE_INT, 8],
        "status" => [\swoole_table::TYPE_INT, 1],
        "start" => [\swoole_table::TYPE_INT, 8],
        "end" => [\swoole_table::TYPE_INT, 8],
        "code"=> [\swoole_table::TYPE_INT, 1],
    ];
    const PROCESS_START = 0;//程序开始运行
    const PROCESS_STOP = 1;//程序结束运行

    public $task;

    public static function init()
    {
        $conf = \Swoole::$php->config["crontab"];
        $robot_process_max = (isset($conf["robot_process_max"]) && $conf["robot_process_max"] > 0) ? $conf["robot_process_max"] : 128;
        self::$table = new \swoole_table($robot_process_max);
        foreach (self::$column as $key => $v) {
            self::$table->column($key, $v[0], $v[1]);
        }
        self::$table->create();
    }

    public static function signal()
    {
        \swoole_process::signal(SIGCHLD, function($sig) {
            //必须为false，非阻塞模式
            while($ret =  \swoole_process::wait(false)) {
                $pid = $ret['pid'];
                if (self::$table->exist($pid)){
                    self::$table->set($pid,["status"=>self::PROCESS_STOP,"end"=>microtime(true),"code"=>$ret["code"]]);
                }
            }
        });
    }
    /**
     * 创建一个子进程
     * @param $task
     */
    public static function create_process($task)
    {
        $cls = new self();
        $cls->task = $task;
        $process = new \swoole_process(array($cls, "run"));
        if (($pid = $process->start())) {
            self::$table->set($pid,["taskId"=>$task["id"],"status"=>self::PROCESS_START,"start"=>microtime(true)]);
        }
    }

    /**
     * 子进程执行的入口
     * @param $worker
     */
    public function run($worker)
    {
        $exec = $this->task["execute"];
        $worker->name($exec ."#". $this->task["id"]);
        $exec = explode(" ",$exec);
        $execfile = $exec[0];
        unset($exec[0]);
        $worker->exec($execfile,$exec);
    }
}