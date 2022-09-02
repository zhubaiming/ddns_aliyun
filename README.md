# 欢迎使用阿里云平台动态域名解析服务(DDNS)

---

> 本脚本采用 PHP 语言进行开发
>
> \* 运行本脚本前，请务必确保运行系统中已安装 `net-tools` 工具
>
> \* 运行本脚本前，请在使用 [composer](https://getcomposer.org/) 安装扩展包
>
> \* 如需使用数据库进行ip地址记录，请自行安装数据库扩展并编写SQL语句，本脚本数据库连接采用PDO方式，请确保对应数据库扩展已安装
>
> \* 请自行编写定时任务脚本以实现定时更新域名解析
>
> 本脚本可在 [docker](https://www.docker.com/) 容器中运行，请自行安装docker并运行容器（**后续会更新容器镜像**）

###### 已测试系统
- [x] Linux
- [x] MacOS
- [ ]Windows

---

### 脚本运行

```shell
php run.php --type= --rr= --domainname= [--remark=]
```

##### 参数说明

|参数|类型|必填|描述|示例|
|:---|:---|:---|:---|:---|
|type|string|是|解析记录类型：A - IPv4，AAAA - IPv6|A|
|rr|string|是|解析主机记录，可同时解析多个，使用','分割|@,www|
|domainname|string|是|域名名称|xxx.com|
|remark|string|否|解析记录备注，可同时填写多个，使用','分割，顺序对应 rr 顺序|备注1,备注2|
