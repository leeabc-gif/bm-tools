# BM TOOLS SSH 服务器监控商业版开发方案

本文档用于规划 BM TOOLS 在现有“宝塔 API 监控”之外，新增“SSH 直连监控”的商业版实现方案。

## 一、功能定位

现有监控能力：

- 通过宝塔面板 API 获取服务器状态。
- 适合已经安装宝塔面板，并开启 API 的服务器。

新增 SSH 监控后：

- 管理员输入服务器 IP、SSH 端口、用户名、密码或密钥。
- 系统通过 SSH 登录 Linux 服务器执行只读命令。
- 采集 CPU、内存、磁盘、网络、负载、在线时间、系统版本等状态。
- 数据写入现有 `monitor_metrics` 表。
- 前台 `/status` 继续统一展示服务器状态，不区分用户看到的是宝塔 API 还是 SSH 采集。

商业价值：

- 覆盖未安装宝塔面板的 Linux 服务器。
- 作为宝塔 API 不稳定、API 白名单配置失败时的备用采集通道。
- 让 BM TOOLS 从“宝塔状态展示”升级为“多协议服务器监控平台”。

## 二、推荐技术路线

推荐优先使用 `phpseclib/phpseclib`。

原因：

- 纯 PHP 实现 SSH2，不强依赖服务器安装 PECL ssh2 扩展。
- 更适合宝塔虚拟主机或普通 PHP 7.4 环境部署。
- Composer 安装即可使用。
- 支持密码登录、密钥登录、执行远程命令。

建议版本：

```bash
composer require phpseclib/phpseclib:~3.0
```

兼容性说明：

- phpseclib 3.0 是长期支持版本。
- Packagist 显示 3.0 分支最低 PHP 版本为 5.6.1，兼容本项目 PHP 7.4。

备用方案：

- 如果服务器安装了 PECL `ssh2` 扩展，也可以用 `ssh2_connect`、`ssh2_auth_password`、`ssh2_exec`。
- 但商业版不建议把 PECL ssh2 作为唯一方案，因为宝塔环境经常没有默认安装。

## 三、数据库扩展

推荐扩展 `monitor_servers` 表，而不是新建一套 SSH 表。

新增字段：

```sql
ALTER TABLE `bm_monitor_servers`
  ADD COLUMN `monitor_type` enum('bt','ssh') NOT NULL DEFAULT 'bt' AFTER `user_id`,
  ADD COLUMN `ssh_host` varchar(120) DEFAULT NULL AFTER `server_ip`,
  ADD COLUMN `ssh_port` int unsigned NOT NULL DEFAULT 22 AFTER `ssh_host`,
  ADD COLUMN `ssh_username` varchar(80) DEFAULT NULL AFTER `ssh_port`,
  ADD COLUMN `ssh_auth_type` enum('password','key') NOT NULL DEFAULT 'password' AFTER `ssh_username`,
  ADD COLUMN `ssh_password` text DEFAULT NULL AFTER `ssh_auth_type`,
  ADD COLUMN `ssh_private_key` mediumtext DEFAULT NULL AFTER `ssh_password`,
  ADD COLUMN `ssh_key_passphrase` text DEFAULT NULL AFTER `ssh_private_key`,
  ADD COLUMN `ssh_fingerprint` varchar(160) DEFAULT NULL AFTER `ssh_key_passphrase`;
```

说明：

- `monitor_type = bt`：继续使用宝塔 API。
- `monitor_type = ssh`：使用 SSH 采集。
- `ssh_password`、`ssh_private_key`、`ssh_key_passphrase` 必须加密保存，不能明文入库。

商业版建议：

- 后台显示时永远不回显真实密码。
- 编辑时密码留空表示不修改。
- 支持“测试连接”。
- 支持“保存后立即采集”。

## 四、配置加密方案

新增配置：

```php
// config/app.php
'security' => [
    'secret_key' => '安装时生成的 32 字节随机密钥',
],
```

安装时生成：

```php
bin2hex(random_bytes(32))
```

加密函数：

- `openssl_encrypt`
- AES-256-CBC 或 AES-256-GCM
- 每条密文独立 IV

建议保存格式：

```text
base64(iv):base64(ciphertext)
```

安全要求：

- 数据库泄露时，SSH 密码不能直接暴露。
- `config/app.php` 和 `config/database.php` 必须要求可写后改为只读。
- 后台导出功能不能导出 SSH 密码或私钥。
- 管理员日志不能记录 SSH 密码。

## 五、后台页面改造

后台监控管理页新增“监控方式”：

- 宝塔 API
- SSH 直连

当选择宝塔 API：

- 显示宝塔面板地址
- 宝塔 API 密钥
- 面板端口

当选择 SSH 直连：

- 显示服务器 IP
- SSH 端口
- SSH 用户名
- 认证方式
- SSH 密码
- SSH 私钥
- 私钥口令
- Host Key 指纹确认

后台按钮：

- 测试连接
- 保存服务器
- 保存后立即采集
- 立即采集
- 暂停监控
- 删除服务器

商业 UX：

- SSH 密码框提示“留空表示不修改”。
- 第一次连接成功后保存服务器指纹，后续指纹变化时提示风险。
- 连接失败返回明确原因：端口不通、认证失败、命令不可用、权限不足。

## 六、SSH 采集命令设计

SSH 只执行只读命令，不能执行危险命令。

建议单次执行一个组合命令，输出 JSON，减少 SSH 往返：

```bash
printf '{';
printf '"uptime":"%s",' "$(uptime -p 2>/dev/null | sed 's/"/\\"/g')";
printf '"load":"%s",' "$(cat /proc/loadavg 2>/dev/null | awk '{print $1","$2","$3}')";
printf '"mem_total":%s,' "$(awk '/MemTotal/ {print $2*1024}' /proc/meminfo)";
printf '"mem_available":%s,' "$(awk '/MemAvailable/ {print $2*1024}' /proc/meminfo)";
printf '"cpu_line_1":"%s",' "$(head -n1 /proc/stat)";
sleep 1;
printf '"cpu_line_2":"%s",' "$(head -n1 /proc/stat)";
printf '"disk":[';
df -B1 -P | awk 'NR>1 {printf "%s{\"path\":\"%s\",\"total\":%s,\"used\":%s}", sep, $6, $2, $3; sep=","}';
printf '],';
printf '"network":[';
cat /proc/net/dev | awk 'NR>2 {gsub(":","",$1); printf "%s{\"iface\":\"%s\",\"rx\":%s,\"tx\":%s}", sep, $1, $2, $10; sep=","}';
printf ']';
printf '}';
```

CPU 计算：

- 第一次读取 `/proc/stat`。
- 等 1 秒。
- 第二次读取 `/proc/stat`。
- 计算总 CPU 时间差和 idle 时间差。
- CPU 使用率 = `(total_delta - idle_delta) / total_delta * 100`。

内存计算：

- `MemTotal`
- `MemAvailable`
- 已用 = `MemTotal - MemAvailable`
- 使用率 = `used / total * 100`

磁盘计算：

- `df -B1 -P`
- 每个挂载点作为一个硬盘分区。
- 前台 `/status` 显示多条硬盘分区进度条。

网络计算：

- 单次采集拿到 `/proc/net/dev` 的累计 rx/tx。
- 与上一次 `raw_data` 中的网络累计值和时间做差。
- 得到上传/下载速度。

## 七、代码结构建议

新增文件：

```text
app/Services/Monitor/SshMonitorClient.php
app/Services/Monitor/SshMonitorCollector.php
app/Services/CryptoService.php
```

改造文件：

```text
app/Services/Monitor/MonitorService.php
app/Controllers/AdminController.php
app/Views/admin/monitors.php
database/schema.sql
app/Views/home/status_page.php
```

`MonitorService` 调度逻辑：

```php
if ($server['monitor_type'] === 'ssh') {
    $metric = (new SshMonitorCollector())->collect($server);
} else {
    $metric = $this->collectByBtApi($server);
}
```

## 八、异常与安全控制

必须实现：

- SSH 连接超时。
- 命令执行超时。
- 命令输出最大长度限制。
- JSON 解析失败处理。
- 禁止用户自定义命令。
- 禁止普通用户添加 SSH 监控。
- 后台只允许管理员配置 SSH 监控。
- 密码、私钥、API Key 全部加密保存。
- 采集失败写入 `last_error`。
- 采集失败生成告警。

建议默认超时：

- SSH 连接超时：8 秒
- 命令执行超时：15 秒
- 输出限制：64 KB

## 九、商业版实现分期

第一期：可用版

- 数据库新增 SSH 字段。
- 后台新增监控方式选择。
- 支持 SSH 密码登录。
- 支持测试连接。
- 支持 CPU、内存、磁盘、负载、在线时间采集。
- 写入现有 `monitor_metrics`。
- 前台 `/status` 统一展示。

第二期：增强版

- 支持 SSH 私钥登录。
- 支持 Host Key 指纹确认。
- 支持网络实时速度计算。
- 支持系统版本、内核版本、发行版展示。
- 支持采集失败重试。

第三期：商业运营版

- 监控模板。
- 服务器分组。
- 多区域节点。
- 可用率统计。
- SLA 展示。
- 告警渠道：邮件、企业微信、Telegram、钉钉。
- 监控数据清理策略。
- 异常节点自动置顶。

## 十、结论

可以实现。

BM TOOLS 最终监控能力建议定位为：

```text
宝塔 API 监控 + SSH 直连监控 + 前台公开状态页 + 后台告警管理
```

这样商业价值更强：

- 宝塔用户开箱即用。
- 非宝塔 Linux 服务器也能接入。
- 运营者能统一展示服务健康状态。
- 前台用户看到的是稳定、可信的服务器状态中心。

## 十一、当前已落地的第一期代码

本轮已完成第一期基础代码：

- 前台普通导航已移除“运营管理 / 进入后台”入口。
- 新增 `app/Services/CryptoService.php`，用于加密保存 SSH 密码和私钥。
- 新增 `app/Services/Monitor/SshMonitorClient.php`。
- 新增 `app/Services/Monitor/SshMonitorCollector.php`。
- `MonitorService` 已支持按 `monitor_type` 自动选择宝塔 API 或 SSH 采集。
- `database/schema.sql` 已新增 SSH 监控字段。
- 后台监控管理页已增加“宝塔 API / SSH 直连”切换表单。
- 安装流程会自动生成 `security.secret_key`。

当前运行要求：

- 推荐安装：`composer require phpseclib/phpseclib:^3.0`
- 如果未安装 phpseclib，但 PHP 环境有 `ssh2` 扩展，则可使用密码模式备用通道。
- 如果二者都没有，系统会在测试连接或采集时提示明确安装方式，不会白屏。

下一步建议：

- 增加数据库升级脚本，方便旧站从宝塔监控升级为双通道监控。
- 增加 Host Key 指纹确认，防止中间人风险。
- 后台监控列表增加“监控方式”列。
- 前台服务器状态页展示 `SSH` 或 `BT` 来源标签。
