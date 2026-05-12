# BM TOOLS HTTP 探针监控部署说明

HTTP 探针监控用于解决主站环境没有 SSH 客户端的问题。

你不需要在主站安装：

- Composer
- phpseclib
- PHP ssh2 扩展

只需要在被监控服务器放一个 PHP 探针文件即可。

## 一、适合场景

- 用户觉得 `composer require phpseclib/phpseclib:^3.0` 麻烦。
- 宝塔 API 没开启或白名单不好配置。
- 被监控服务器可以通过 HTTP/HTTPS 访问一个 PHP 文件。
- 运营方只想快速展示 CPU、内存、磁盘、网络和负载。

## 二、部署步骤

### 第一步：复制探针文件

主站项目里已经提供：

```text
public/monitor-agent.php
```

把这个文件上传到被监控服务器的网站目录，例如：

```text
/www/wwwroot/server-status.example.com/monitor-agent.php
```

### 第二步：修改 Token

打开探针文件，修改顶部：

```php
$token = 'CHANGE_THIS_TOKEN';
```

改成随机强密码，例如：

```php
$token = 'BM_9d7f8a2c6e1b4f3a';
```

### 第三步：浏览器测试

访问：

```text
https://server-status.example.com/monitor-agent.php?token=BM_9d7f8a2c6e1b4f3a
```

正常会返回 JSON：

```json
{
  "success": true,
  "source": "bmtools-agent",
  "cpu_usage": 3.12,
  "memory_usage": 28.5
}
```

### 第四步：后台添加服务器

进入：

```text
后台管理 -> 监控管理
```

监控方式选择：

```text
HTTP 探针（免 SSH 依赖）
```

填写：

```text
HTTP 探针地址：https://server-status.example.com/monitor-agent.php
探针 Token：BM_9d7f8a2c6e1b4f3a
```

点击：

```text
测试连接
```

测试成功后保存即可。

## 三、安全建议

- Token 一定要改，不要使用默认值。
- 建议给探针站点开启 HTTPS。
- 不建议把探针 URL 公开给普通用户。
- 可以在 Nginx 里限制只允许主站 IP 访问探针。
- 探针只读取服务器状态，不执行外部传入命令。

## 四、采集能力

当前 HTTP 探针支持：

- CPU 使用率
- 内存总量
- 内存已用
- 内存使用率
- 硬盘分区
- 硬盘使用率
- 网络上传 / 下载累计值
- 主站计算网络实时速度
- 系统负载
- 在线时长
- PHP 版本
- 主机名
- 系统内核信息

## 五、和其他监控方式对比

| 方式 | 主站依赖 | 被监控服务器要求 | 适合场景 |
|---|---|---|---|
| 宝塔 API | curl | 安装宝塔并开启 API | 宝塔服务器 |
| SSH 直连 | phpseclib 或 ssh2 | 开放 SSH | 专业运维 |
| HTTP 探针 | curl | 可访问 PHP 探针 | 商业傻瓜部署 |

推荐商业默认：

```text
优先推荐 HTTP 探针，其次宝塔 API，高级用户再用 SSH。
```
