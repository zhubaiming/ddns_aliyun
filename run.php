<?php

use Darabonba\OpenApi\Models\Config;
use AlibabaCloud\SDK\Alidns\V20150109\Alidns;
use AlibabaCloud\Tea\Utils\Utils\RuntimeOptions;
use AlibabaCloud\SDK\Alidns\V20150109\Models\DescribeDomainRecordsRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\AddDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\UpdateDomainRecordRemarkRequest;
use AlibabaCloud\SDK\Alidns\V20150109\Models\SetDomainRecordStatusRequest;

Class Ddns {

    private $conn;

    private array $ipv4NetworkOperators = array();

    private array $ipv6NetworkOperators = array(
        '240e' => '中国电信',
        '2408' => '中国联通',
        '2409' => '中国移动'
    );

    private string $connType = 'xxxxxxxx'; // 替换成你自己的数据库类型

    private string $connHost = 'xxxxxxxx'; // 替换成你自己的数据库连接地址

    private string $connPort = 'xxxxxxxx'; // 替换成你自己的数据库连接端口

    private string $connCharset = 'utf8mb4'; // 数据库字符集编码，可替换成你自己想要的编码

    private string $connDbname = 'xxxxxxxx'; // 替换成你自己的数据库名

    private string $connUserName = 'xxxxxxxx'; // 替换成你自己的数据库连接用户

    private string $connPassword = 'xxxxxxxx'; // 替换成你自己的数据库连接密码

    private string $accessKeyId = 'xxxxxxxx'; // 替换成你自己的阿里云AccessKey ID

    private string $accessKeySecret = 'xxxxxxxx'; // 替换成你自己的阿里云AccessKey Secret

    public function __construct(string $type, string $rr, string $domainName, string $remark) {
        $ip = $this->validateArgs($type);
        if (!is_null($ip)) {
            $this->databaseConnect(); // 如果不需要数据库记录，删除当前行和下一级判断，只保留下一级判断内的调用即可
            if (!$this->compareIpRecord($ip, $type)) {
                $this->main($type, $rr, $domainName, $ip, $remark);
            }
        } else {
            outputLog('error', '当前类型下(' . $type . ')，没有可供解析的外网ip!');
        }
    }

    /**
     * 获取要解析的类型
     *
     * @return string 返回值为解析的网卡ip地址
     */
    private function validateArgs($type) {
        return match($type) {
            'A' => $this->getIPv4Addr(),
            'AAAA' => $this->getIPv6Addr(),
            default => null
        };
    }

    /**
     * 获取网卡ipv4外网地址
     *
     * 1、使用 shell_exec() 执行shell命令，获取网卡ip，并使用 [grep] 命令进行内容过滤
     * 2、使用 preg_replace() 按照正则表达式规则进行截取
     *
     * @return string 返回值为ipv4地址
     */
    private function getIPv4Addr() {
        $ip = shell_exec("ifconfig enp2s0 | grep inet | grep -vE 'inet6|127|172|192|100|10'");
    
        return is_null($ip) ? $ip : trim(preg_replace(array('/inet/', '/(\s+)[a-z](.*)/'), '', trim($ip)));
    }

    /**
     * 获取网卡ipv6外网地址
     *
     * 1、使用 shell_exec() 执行shell命令，获取网卡ip，并使用 [grep] 命令进行内容过滤
     * 2、使用 preg_replace() 按照正则表达式规则进行截取
     *
     * @return string 返回值为ipv6地址
     */
    private function getIPv6Addr() {
        $ip = shell_exec("ifconfig enp2s0 | grep inet6 | grep -vE 'fe80|fec0|fc00'");

        return is_null($ip) ? $ip : trim(preg_replace(array('/inet6/', '/prefixlen(.*)/'), '', trim($ip)));
    }

    private function databaseConnect() {
        try {
            $this->conn = new PDO($this->connType . ':host=' . $this->connHost . ';port=' . $this->connPort . ';dbname=' . $this->connDbname . ';charset=' . $this->connCharset, $this->connUserName, $this->connPassword);
            $this->conn->exec('SET names ' . $this->connCharset);
        } catch (PDOException $e) {
            outputLog('error', '数据库连接失败!失败原因：' . $e->getMessage());
        }
    }

    private function compareIpRecord(string $ip, $ipType) {
        $sql = ""; // 如果使用数据库记录，请根据你自己的数据库设计格式去改写 SQL 语句(查询语句，查询最新一次更新的ip地址)
        $result = $this->conn->query($sql)->fetch(PDO::FETCH_LAZY);
        $oldIp = $result ? $result->ip_addr : null;

        $networkOperator = $this->getNetworkOperator($ip);

        try {
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->beginTransaction();

            $sql = ""; // 如果使用数据库记录，请根据你自己的数据库设计格式去改写 SQL 语句(插入语句，插入当前货获取到的ip地址)
            $this->conn->exec($sql);

            $this->conn->commit();
        
            $sql = null;
            $this->conn = null;
        
            return ($ip === $oldIp) ? true : false;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            outputLog('error', '数据更新失败!失败原因：' . $e->getMessage());
        }
    }
    
    /**
     * 获取网络运营商类型
     *
     * @return string 网络运营商名称
     */
    private function getNetworkOperator($ip) {
        $ips = explode(':', $ip);

        return match(strlen($ips[0])) {
            3 => $this->ipv4NetworkOperators[$ips[0]],
            4 => $this->ipv6NetworkOperators[$ips[0]]
        };
    }

    /**
     * 使用AK&SK初始化账号Client
     *
     * @param string $accessKeyId
     * @param string $accessKeySecret
     *
     * @return Alidns Client
     */
    private function createClient() {
        $config = new Config(array(
            'accessKeyId' => $this->accessKeyId,
            'accessKeySecret' => $this->accessKeySecret
        ));
        $config->endpoint = '';
        return new Alidns($config);
    }

    /**
     * 获取解析记录表
     *
     *
     *
     *
     *
     */
    private function aliDescribeDomainRecords($domainName, Alidns $client, RuntimeOptions $runtime) {
        $describeDomainRecordsRequest = new DescribeDomainRecordsRequest(array(
            'lang' => 'zh',
            'domainName' => $domainName,
            'pageNumber' => 1,
            'pageSize' => 500
        ));
        try {
            return $client->describeDomainRecordsWithOptions($describeDomainRecordsRequest, $runtime);
        } catch (Exception $e) {
            outputLog('error', '阿里云错误：' . $e->getMessage());
        }
    }
    
    /**
     * 添加解析记录
     *
     */
    private function aliAddDomainRecord($domainName, $rr, $type, $ip, Alidns $client, RuntimeOptions $runtime) {
        $addDomainRecordRequest = new AddDomainRecordRequest(array(
            'lang' => 'zh',
            'domainName' => $domainName,
            'RR' => $rr,
            'type' => $type,
            'value' => $ip
        ));
        try {
            return $client->addDomainRecordWithOptions($addDomainRecordRequest, $runtime);
        } catch (Exception $e) {
            outputLog('error', '阿里云错误：' . $e->getMessage());
        }
    }

    /**
     * 修改域名解析记录
     *
     */
    private function aliUpdateDomainRecord(string $recordId, $rr, $type, $ip, Alidns $client, RuntimeOptions $runtime) {
        $updateDomainRecordRequest = new UpdateDomainRecordRequest(array(
            'lang' => 'zh',
            'recordId' => $recordId,
            'RR' => $rr,
            'type' => $type,
            'value' => $ip
        ));
        try {
            return $client->updateDomainRecordWithOptions($updateDomainRecordRequest, $runtime);
        } catch (Exception $e) {
            outputLog('error', '阿里云错误：' . $e->getMessage());
        }
    }

    /**
     * 修改解析记录备注
     *
     */
    private function aliUpdateDomainRecordRemark(string $recordId, Alidns $client, RuntimeOptions $runtime, string $remark='') {
        $updateDomainRecordRemarkRequest = new UpdateDomainRecordRemarkRequest(array(
            'lang' => 'zh',
            'recordId' => $recordId,
            'remark' => $remark
        ));
        try {
            return $client->updateDomainRecordRemarkWithOptions($updateDomainRecordRemarkRequest, $runtime);
        } catch (Exception $e) {
            outputLog('error', '阿里云错误：' . $e->getMessage());
        }
    }
    
    /**
     * 设置解析记录状态
     *
     */
    private function aliSetDomainRecordStatus(string $recordId, Alidns $client, RuntimeOptions $runtime) {
        $setDomainRecordStatusRequest = new SetDomainRecordStatusRequest(array(
            'lang' => 'zh',
            'recordId' => $recordId,
            'status' => 'Enable'
        ));
        try {
            return $client->setDomainRecordStatusWithOptions($setDomainRecordStatusRequest, $runtime);
        } catch (Exception $e) {
            outputLog('error', '阿里云错误：' . $e->getMessage());
        }
    }

    /**
     *
     */
    private function main($type, $rr, $domainName, $ip, $remark) {
        $client = $this->createClient();
        $runtime = new RuntimeOptions([]);

        $list = $this->aliDescribeDomainRecords($domainName, $client, $runtime)->toMap()['body']['DomainRecords']['Record'];

        $rr = explode(',', $rr);
        $remark = explode(',', $remark);
        $RR = array_column($list, 'RR');

        foreach ($rr as $key => $value) {
            if (in_array($value, $RR)) {
                $RecordId = $list[array_search($value, $RR)]['RecordId'];
                $this->aliUpdateDomainRecord($RecordId, $value, $type, $ip, $client, $runtime);
            } else {
                $RecordId = $this->aliAddDomainRecord($domainName, $value, $type, $ip, $client, $runtime)->toMap()['body']['RecordId'];
            }

            $this->aliUpdateDomainRecordRemark($RecordId, $client, $runtime, $remark[$key]??'');
            $this->aliSetDomainRecordStatus($RecordId, $client, $runtime);
        }
    }
}

function outputLog(string $level, string $message, int $exit=1) {
    echo '[' . date('Y-m-d H:i:s') . '] - [' . $level . '] - ' . $message . PHP_EOL;
    exit($exit);
}

$args = getopt('', array('type:', 'rr:', 'domainname:', 'remark::'));

if (!isset($args['type']) || !isset($args['rr']) || !isset($args['domainname'])) {
    outputLog('error', '缺少必要参数，程序无法继续执行!');
}

$path = __DIR__ . \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($path)) {
    require $path;
} else {
    outputLog('error', '扩展包不存在或加载路径不正确，请调整后再试!');
}

$class= new Ddns($args['type'], $args['rr'], $args['domainname'], $args['remark']??'');
outputLog('success', 'ip地址更新完成，并完成解析!', 0);

?>
