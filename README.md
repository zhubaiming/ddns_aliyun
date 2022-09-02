# 欢迎使用阿里云平台动态域名解析服务(DDNS)

> **使用者请自行编写定时任务或shell脚本**

## 使用方式：
```shell
php run.php --type= --rr= --domainname= [--remark=]
```
    参数说明：
    |参数|类型|必填|描述|示例|
    |:-|:-|:-|:-|:-|
    |type|string|是|解析记录类型：A - IPv4，AAAA - IPv6|A|
    |rr|string|是|解析主机记录，可同时解析多个，使用','分割|@,www|
    |domainname|string|是|域名名称|xxx.com|
    |remark|string|否|解析记录备注，可同时填写多个，使用','分割，顺序对应 rr 顺序|备注1,备注2|
