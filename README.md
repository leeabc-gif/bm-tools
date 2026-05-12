# BM TOOLS

BM TOOLS 是一个 PHP 7.4 + MySQL 5.7 的综合型图床、轻量网盘、充值套餐、多对象存储和运营方服务器状态监控系统。

## 已实现模块

- 安装向导：访问 `/install`，完成环境检测、数据库配置、管理员创建、初始数据写入和安装锁生成。
- 用户系统：注册、登录、记住登录、找回密码验证码、资料修改、头像 URL、邮箱修改、密码修改、登录日志、管理员/普通用户权限隔离。
- 套餐充值：套餐列表、余额购买、续费/升级差价抵扣、人工充值审核、订单记录、支付配置预留。
- 支付宝当面付：支持 `alipay.trade.precreate` 扫码下单、异步通知验签和充值自动入账。
- 图床：单图/多图上传、拖拽/粘贴前端交互、URL 拉取、外链格式、图片列表和批量删除。
- 网盘：文件夹、任意文件上传、基础预览、分享链接、提取码、有效期、分享记录。
- 对象存储：统一 `StorageInterface`，支持阿里云 OSS、腾讯云 COS、七牛云 Kodo 的基础上传/删除/URL 生成。
- 本地测试存储：安装后默认启用，可在未配置云存储时先完成全流程测试。
- 服务器监控：运营方在后台维护宝塔 API 节点，前台公开展示服务器状态，支持测试连接、手动/定时采集、指标入库、告警和站内/邮件通知。
- 公告通知：公告列表/详情、后台公告管理、站内通知、一键已读。
- 后台管理：用户、套餐、订单、充值、支付、存储、文件、监控、公告、系统设置、管理员日志。
- 前端 UI：玻璃拟态、深浅色主题、响应式布局、滚动动画、自定义右键菜单、鼠标样式、回到顶部、上传交互。
- 商业 UI 增强：后台菜单和按钮已接入 Lucide 图标，支持点击涟漪、页面淡入/淡出、提交 loading、菜单高亮和表格 hover 动效。

## 宝塔部署

1. 创建 PHP 7.4 站点，MySQL 版本建议 5.7。
2. 上传项目到站点目录。
3. 推荐将网站运行目录设置为 `public`；如果不设置，根目录 `.htaccess` 也会转发到 `public/index.php`。
4. 确认 `config`、`storage`、`storage/logs`、`storage/cache`、`public/uploads` 可写。
5. 访问 `https://你的域名/install` 完成安装。
6. 安装后会生成 `storage/install.lock`，系统会禁止重复安装。

如果宝塔使用 Nginx，可把 `bt-nginx-rewrite.conf` 中的规则放入站点伪静态配置。Apache 环境已提供 `.htaccess`。

安装完成后默认会启用“本地测试存储”，文件会写入 `public/uploads/files`。正式运营建议在后台添加 OSS/COS/Kodo，并设置为默认存储源。

## 定时监控

在宝塔计划任务中添加 Shell 或 PHP 脚本任务，按需每 1 分钟执行：

```bash
php /www/wwwroot/你的站点/cron_monitor.php
```

被监控服务器必须安装宝塔面板，并在面板设置中开启 API、配置 API 密钥和允许主站 IP。

监控可用性检查清单：

1. 被监控服务器已安装宝塔面板。
2. 宝塔面板设置中已开启 API。
3. 主站服务器 IP 已加入宝塔 API 白名单。
4. 面板地址可从主站访问，示例：`http://1.2.3.4:8888` 或 `https://panel.example.com:8888`。
5. 防火墙和安全组已放行面板端口。
6. 后台“监控管理”中添加运营方服务器，并开启监控。
7. 宝塔计划任务中每分钟执行 `cron_monitor.php`。

监控模块会采集 CPU、内存、磁盘、网络、系统负载、在线时长，以及 Nginx、MySQL、PHP-FPM、面板状态。连接失败、API 未开启、IP 白名单未放行、返回非 JSON 等错误会写入 `monitor_servers.last_error` 和 `storage/logs`。

邮件通知支持后台配置 SMTP。端口 465 默认走 SSL，端口 587 会尝试 STARTTLS。

## 支付宝当面付

后台进入“支付配置”，启用 `alipay`，配置 JSON 示例：

```json
{
  "app_id": "支付宝开放平台应用 AppID",
  "private_key": "应用私钥，支持一行或 PEM 格式",
  "alipay_public_key": "支付宝公钥，支持一行或 PEM 格式",
  "sandbox": false,
  "notify_url": "https://你的域名/pay/alipay/notify",
  "timeout_express": "10m"
}
```

充值时选择“支付宝当面付”会调用 `alipay.trade.precreate` 创建扫码订单。支付宝异步通知会自动验签，并在 `TRADE_SUCCESS` 或 `TRADE_FINISHED` 时给用户余额入账。

## API 上传

用户可在个人资料页查看 API Token。套餐需开启 API 上传。

```bash
curl -X POST https://你的域名/api/upload \
  -H "X-API-Token: 用户TOKEN" \
  -F "type=image" \
  -F "file=@demo.jpg"
```

更多 API 能力：

- `POST /api/upload` 上传图片或文件
- `POST /api/files` 获取文件列表，参数：`page`、`limit`、`type`、`keyword`
- `POST /api/delete` 删除文件，参数：`id`
- `POST /api/quota` 获取容量和余额信息

所有 API 均支持 Header：`X-API-Token: 用户TOKEN`。后台系统设置可调整每分钟和每日 API 调用限制。用户中心的“API 管理”页面提供 PicGo、ShareX 和 curl 配置示例。

## 升级说明

如果你已经安装过旧版本，本次商业化增强新增了余额流水、API 日志和文件操作日志表。请把 `database/upgrade_20260509_commercial.sql` 中的 `__PREFIX__` 替换成你的实际表前缀后执行。

## 说明

当前对象存储驱动采用轻量 curl 实现，适合宝塔环境快速落地。生产环境如需更完整的分片上传、私有读签名、防盗链、图片处理和 CDN 刷新，可以在 `app/Services/Storage` 内替换为官方 SDK，控制器和业务层不需要大改。
