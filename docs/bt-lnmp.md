# 宝塔面板 + LNMP 完整教程

> 面向用宝塔部署和运维 WordPress / WooCommerce 站点的开发者
> 配套阅读：`wordpress-core.md`、`wordpress-theme.md`、`woocommerce.md`

这份文档的立场：**宝塔是个 Web 界面，底下还是标准的 Nginx + PHP-FPM + MySQL**。面板能点的每一件事都对应着一条命令或一个配置文件。只会点面板的人，在面板出问题的那天就没辙了；理解底下那一层，面板只是让你少打点字。

所以每一节的结构都是：**原理 → 面板怎么点 → 底下实际发生了什么 → 命令行怎么做同样的事**。

---

## 目录

1. [LNMP 是什么：一个请求走完整圈](#1-lnmp-是什么一个请求走完整圈)
2. [服务器选型与系统初始化](#2-服务器选型与系统初始化)
3. [安装宝塔面板](#3-安装宝塔面板)
4. [面板安全加固（装完立刻做）](#4-面板安全加固装完立刻做)
5. [宝塔到底改了你的系统什么](#5-宝塔到底改了你的系统什么)
6. [Nginx：原理与配置结构](#6-nginx原理与配置结构)
7. [Nginx + WordPress 实战配置](#7-nginx--wordpress-实战配置)
8. [PHP-FPM：进程模型与调优](#8-php-fpm进程模型与调优)
9. [MySQL / MariaDB：配置与调优](#9-mysql--mariadb配置与调优)
10. [建站完整流程](#10-建站完整流程)
11. [HTTPS 与证书](#11-https-与证书)
12. [缓存体系：OPcache / Redis / FastCGI Cache](#12-缓存体系opcache--redis--fastcgi-cache)
13. [文件权限与属主](#13-文件权限与属主)
14. [备份与恢复](#14-备份与恢复)
15. [站点迁移](#15-站点迁移)
16. [定时任务](#16-定时任务)
17. [日志：在哪、怎么读](#17-日志在哪怎么读)
18. [排错手册（按症状索引）](#18-排错手册按症状索引)
19. [安全加固](#19-安全加固)
20. [性能压测与容量规划](#20-性能压测与容量规划)
21. [多站点部署与资源隔离](#21-多站点部署与资源隔离)
22. [命令行运维速查](#22-命令行运维速查)
23. [宝塔的坑](#23-宝塔的坑)
24. [不用宝塔：手工 LNMP 对照](#24-不用宝塔手工-lnmp-对照)
25. [速查附录](#25-速查附录)

---

## 1. LNMP 是什么：一个请求走完整圈

LNMP = **L**inux + **N**ginx + **M**ySQL + **P**HP。

关键点在于：**Nginx 不会执行 PHP**。它和 Apache 不一样，没有 `mod_php` 这种把 PHP 解释器塞进 Web 服务器进程里的模块。Nginx 遇到 `.php` 文件时，是把请求通过 FastCGI 协议**转交给另一个独立的进程**（PHP-FPM），拿到结果再返回给浏览器。

理解这个分工，一半的排错问题就有方向了。

### 完整链路

```
浏览器
  │  ① HTTP(S) 请求  GET /about/
  ▼
[ 防火墙 :80 :443 ]
  │
  ▼
Nginx  (用户 www，master + N 个 worker 进程)
  │  ② 匹配 server 块（按 server_name）
  │  ③ 匹配 location
  │     - 静态文件（.jpg .css .js）→ 直接读磁盘返回，PHP 完全不参与
  │     - try_files 找不到实体文件 → 重写到 /index.php （WordPress 伪静态）
  │  ④ 命中 .php → 通过 FastCGI 转发
  ▼
  fastcgi_pass unix:/tmp/php-cgi-82.sock   （Unix socket，本机进程间通信）
  │
  ▼
PHP-FPM (用户 www，master + N 个 worker 进程)
  │  ⑤ master 把请求派给一个空闲 worker
  │  ⑥ worker 执行 index.php
  │     └─ OPcache 命中？命中就跳过「读文件 + 词法 + 语法 + 编译」
  │  ⑦ WordPress 启动 → 解析 URL → 查数据库
  ▼
MySQL / MariaDB  (用户 mysql，:3306 或 unix socket)
  │  ⑧ 返回结果集
  ▼
PHP-FPM  ⑨ 渲染出 HTML 字符串
  ▼
Nginx    ⑩ 加响应头、gzip 压缩
  ▼
浏览器   ⑪ 渲染
```

### 每一环坏了会怎样

这张表值钱，排错时先定位到环节再查：

| 症状 | 断在哪 | 说明 |
|---|---|---|
| 浏览器超时 / 连不上 | 防火墙或 Nginx 没起 | 请求根本没到 Nginx |
| **502 Bad Gateway** | Nginx ↔ PHP-FPM（第 ④⑤ 步） | PHP-FPM 没起、socket 路径错、或 worker 被打满 |
| **504 Gateway Timeout** | PHP 跑太久 | PHP 还在跑，Nginx 等不及了 |
| **500 Internal Server Error** | PHP 内部（第 ⑥ 步） | PHP Fatal error，看 PHP 错误日志 |
| **白屏（200，无内容）** | PHP 内部 | Fatal 但关了错误显示；或模板输出为空 |
| **数据库连接错误** | 第 ⑦⑧ 步 | MySQL 没起、密码错、或连接数打满 |
| 静态文件 404、PHP 页面正常 | 第 ③ 步 | root 路径错，或权限不足 |
| **PHP 源码被浏览器下载** | 第 ④ 步 | Nginx 没配 `fastcgi_pass`，把 .php 当静态文件发了（**严重安全事故**） |

### 为什么是 Nginx 而不是 Apache

- **并发模型**：Apache 的 prefork 是「一个连接一个进程」，1000 个并发连接就是 1000 个进程，内存爆掉。Nginx 是事件驱动，一个 worker 用 epoll 同时管几千个连接，内存占用几乎不随连接数增长。
- **静态文件**：Nginx 直接 `sendfile()`，数据从磁盘到网卡不经过用户态，比 Apache 快一个数量级。
- **代价**：没有 `.htaccess`。Apache 每次请求都会沿着目录树找 `.htaccess`（这也正是它慢的原因之一），Nginx 的规则全在主配置里，**改完必须 reload**。从虚拟主机迁到 Nginx 的人最容易在这里翻车——上传的 `.htaccess` 完全不生效。

---

## 2. 服务器选型与系统初始化

### 配置怎么估

WordPress 站点的内存消耗，一条粗略但够用的公式：

```
所需内存 ≈ 系统 300MB
         + MySQL (innodb_buffer_pool_size + 200MB)
         + PHP-FPM (单进程内存 × pm.max_children)
         + Nginx 50MB
         + Redis（如果用）
```

单个 PHP-FPM 进程跑 WordPress 通常占 **40–80MB**，装了 WooCommerce 和一堆插件能到 **120–200MB**。这是所有容量规划的基准数字。

| 场景 | 配置 | 说明 |
|---|---|---|
| 单个企业站 / 博客，日 PV < 5000 | 2 核 2G | 能跑，但 2G 装完宝塔+MySQL 就只剩 1G 出头，`pm.max_children` 只能开到 8 左右 |
| 常规生产站 | 2 核 4G | **推荐起步**。4G 是个舒服的分水岭 |
| WooCommerce 小店 | 4 核 8G | Woo 的查询重得多，且没法做整页缓存 |
| 多站点 / 中型商城 | 8 核 16G+ | 考虑数据库独立成一台 |

**别在 1G 内存的机器上装宝塔。** 宝塔面板自身（Python 进程）就吃 100–200MB，加上 MySQL，剩下的不够 PHP 跑。硬要跑就必须加 swap，而 swap 上的 MySQL 慢到没有意义。

**磁盘选 SSD/NVMe，不要 HDD。** 数据库是随机小 IO，机械盘上 WordPress 后台会卡到无法使用。

### 系统选哪个

宝塔官方支持 CentOS / Ubuntu / Debian / AlmaLinux / Rocky。

**推荐 Ubuntu 22.04 LTS 或 Debian 12**：

- CentOS 7 已经 EOL（2024-06 停止维护），不要再新装
- CentOS 8 早就 EOL 了
- 要 RHEL 系就用 AlmaLinux 9 / Rocky Linux 9

**必须是纯净系统。** 宝塔的安装脚本假设它是这台机器上第一个装 Nginx/MySQL/PHP 的东西。装在已经有 LNMP 的机器上，轻则端口冲突，重则宝塔的编译安装覆盖掉你原来的配置。

### 初始化（装宝塔之前做）

```bash
# 1. 系统更新
apt update && apt upgrade -y            # Debian/Ubuntu
# dnf update -y                          # AlmaLinux/Rocky

# 2. 时区（日志时间对不上会让排错变得非常痛苦）
timedatectl set-timezone Asia/Shanghai
timedatectl                              # 确认

# 3. 主机名
hostnamectl set-hostname web01

# 4. 基础工具
apt install -y curl wget vim git unzip htop iotop net-tools \
               dnsutils lsof rsync ca-certificates

# 5. 时间同步（证书校验、支付回调都依赖准确时间）
timedatectl set-ntp true
```

### swap：小内存机器必做

内存 ≤ 4G 的机器一定要有 swap。没有 swap 时，内存一满，内核的 OOM killer 会直接杀掉**内存占用最大的进程**——那通常就是 MySQL。表现是「网站突然数据库连接失败，重启一下又好了，过几天再犯」。

```bash
free -h                                  # 先看有没有

swapoff -a 2>/dev/null
fallocate -l 4G /swapfile                # 内存的 1~2 倍，上限 4G 就够
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab

# 降低使用倾向：只在真的快满了才用 swap
sysctl -w vm.swappiness=10
echo 'vm.swappiness=10' >> /etc/sysctl.conf

free -h                                  # 确认 Swap 那行有了
```

`swappiness=10` 的含义：内核只有在可用内存低到约 10% 时才开始换出。默认值 60 会导致明明有内存也把 MySQL 的页换到磁盘上，性能断崖式下跌。

### 文件描述符上限

Nginx 每个连接要占文件描述符，默认的 1024 在稍有并发时就会报 `too many open files`。

```bash
cat >> /etc/security/limits.conf <<'EOF'
* soft nofile 65535
* hard nofile 65535
root soft nofile 65535
root hard nofile 65535
EOF

# systemd 管的服务不读 limits.conf，要单独设
mkdir -p /etc/systemd/system.conf.d
cat > /etc/systemd/system.conf.d/limits.conf <<'EOF'
[Manager]
DefaultLimitNOFILE=65535
EOF

systemctl daemon-reexec
```

**这一步必须在装宝塔前做**，否则装完还要重启所有服务才生效。

### 内核网络参数

```bash
cat >> /etc/sysctl.conf <<'EOF'
# 半连接队列，抗 SYN flood 和突发流量
net.ipv4.tcp_max_syn_backlog = 8192
net.core.somaxconn = 32768
net.core.netdev_max_backlog = 16384

# TIME_WAIT 复用（高并发短连接场景）
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30

# 本地端口范围
net.ipv4.ip_local_port_range = 10000 65000

# 连接跟踪表（配了防火墙才有意义）
net.netfilter.nf_conntrack_max = 262144
EOF

sysctl -p
```

> ⚠️ **不要设 `net.ipv4.tcp_tw_recycle`**。这个参数在 NAT 环境下会导致丢包，Linux 4.12 起已经被移除。网上很多老教程还在教这个，照抄会踩坑。

---
## 3. 安装宝塔面板

### 安装命令

官方安装脚本（以 Ubuntu/Debian 为例）：

```bash
wget -O install.sh https://download.bt.cn/install/install_lts.sh && bash install.sh ed8484bec
```

AlmaLinux / Rocky / CentOS：

```bash
url=https://download.bt.cn/install/install_lts.sh
if [ -f /usr/bin/curl ]; then curl -sSO $url; else wget -O install_lts.sh $url; fi
bash install_lts.sh ed8484bec
```

> 安装命令里的那串字符是官方的校验串，会随版本变。**去 bt.cn 官网复制当前的**，不要照抄任何教程（包括这份）里的旧命令。

国际版是 **aaPanel**（`aapanel.com`），命令类似，界面是英文，功能基本一致。给海外客户做站用 aaPanel，避免国内版的实名认证和绑定手机号要求。

### 安装过程做了什么

脚本会：

1. 装 Python 3 环境和依赖
2. 把面板本体装到 `/www/server/panel/`
3. 建 `/www/` 作为一切东西的根目录
4. 起一个 Python 进程监听面板端口（默认 8888）
5. 装 systemd 服务 `bt`
6. 生成随机的面板地址、用户名、密码

**装完的输出必须立刻保存下来**，包括：

```
外网面板地址: https://1.2.3.4:8888/x7k2m9p1
内网面板地址: https://10.0.0.5:8888/x7k2m9p1
username: xxxxxxxx
password: xxxxxxxxxxxx
```

那串 `x7k2m9p1` 是**安全入口**——没有它，访问 `:8888` 会直接返回 404，这是宝塔最有用的一层保护。忘了的话：

```bash
bt default            # 打印当前的地址 / 用户名 / 入口
```

### 放行端口

云服务器有两层防火墙：**云厂商的安全组** 和 **系统防火墙**。两层都要放行，只开一层是「端口明明开了却连不上」的头号原因。

系统这一层，宝塔会自己处理，手动的话：

```bash
# Ubuntu/Debian (ufw)
ufw allow 8888/tcp && ufw allow 80/tcp && ufw allow 443/tcp

# RHEL 系 (firewalld)
firewall-cmd --permanent --add-port=8888/tcp
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-service=https
firewall-cmd --reload
```

云厂商那一层去控制台的「安全组 / 防火墙规则」里加。**8888 端口的来源建议只放你自己的 IP**，不要 `0.0.0.0/0`。

### 装 LNMP 组件

首次登录面板会弹出推荐安装。选 **LNMP**，然后：

| 组件 | 选择 | 理由 |
|---|---|---|
| Nginx | **1.24 / 1.26 稳定版** | 别选 Tengine，除非你明确需要它的特性；社区资料少 |
| MySQL | **8.0**（新站）或 **5.7**（要兼容老插件） | 见下方说明 |
| PHP | **8.1 / 8.2** | 见下方说明 |
| phpMyAdmin | 装，但装完立刻改端口 + 限 IP | 见第 19 节 |

**编译安装 vs 极速安装**：面板会问。

- **极速安装**：下载预编译的二进制包，2 分钟装完
- **编译安装**：在你的机器上源码编译，30–60 分钟，2 核机器上可能更久

**选极速安装**。编译安装唯一的好处是能针对 CPU 指令集优化，收益在 WordPress 这种 IO 密集型场景下约等于零，却要占满 CPU 一小时。

### PHP 版本怎么选

| 版本 | 状态 | 建议 |
|---|---|---|
| 7.4 | 2022 年已 EOL | 只在被迫兼容老代码时用，且必须尽快升 |
| 8.0 | 已 EOL | 别用 |
| **8.1** | 安全维护期 | **保守选择**，插件兼容性最好 |
| **8.2** | 活跃维护 | **推荐**，WordPress 官方全面支持 |
| 8.3 | 活跃维护 | 可以，但部分老插件会报 deprecated |

WordPress 本身对新版 PHP 很宽容，**风险全在插件和主题上**。升级 PHP 前的标准动作：

1. 装 **PHP Compatibility Checker** 或用 WP-CLI 扫一遍
2. 在测试站切版本跑一遍
3. 生产站切版本后盯着 PHP 错误日志看半小时

宝塔支持多版本共存，每个站点可以单独指定 PHP 版本——这让升级可以一个站一个站地做。

### MySQL 5.7 还是 8.0

- **8.0**：默认字符集 `utf8mb4`，性能更好，但默认认证插件是 `caching_sha2_password`，老的 PHP mysqli 驱动连不上（宝塔装的 PHP 一般没问题）
- **5.7**：兼容性最好，但 2023-10 已经 EOL，不再有安全更新

**新站用 8.0**。老站迁移过来时，先确认插件里没有用 `GROUP BY` 依赖旧 `sql_mode` 的查询——8.0 默认开启 `ONLY_FULL_GROUP_BY`，这会让一部分老插件直接报 SQL 错误。真遇到了：

```sql
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
```

（去掉 `ONLY_FULL_GROUP_BY`；这是妥协，正确做法是修插件。）

---

## 4. 面板安全加固（装完立刻做）

**宝塔面板是整台服务器的 root 权限入口。** 面板被攻破 = 服务器被攻破，不存在「只丢一个站」这种事。历史上宝塔出过多次严重漏洞（最出名的是 2020 年的 phpMyAdmin 未授权访问，`/pma` 直接进数据库）。

下面这些是**装完第一件事**，不是「有空再做」。

### 4.1 改默认端口

面板 → 设置 → 面板端口，改成一个 10000–65000 之间的随机值。

改完**必须同步改云安全组**，否则会把自己锁在外面。

```bash
bt 8            # 命令行改端口（会提示输入新端口）
```

### 4.2 确认安全入口开着

面板 → 设置 → 安全入口。这是最有效的一层——扫描器扫到你的端口，没有入口路径就只能拿到 404。

```bash
bt 14           # 查看 / 设置安全入口
```

**别用有含义的路径**（`/admin`、`/panel`），用随机串。

### 4.3 绑定域名 + 只允许指定 IP

面板 → 设置：

- **域名绑定**：绑一个只有你知道的子域名，绑定后用 IP 访问直接被拒
- **授权 IP**：填你的固定 IP。这是最强的一道，**但如果你是动态 IP，配了会把自己锁死**

> 🔒 动态 IP 的替代方案：不开 IP 白名单，改成把面板端口在云安全组里只对你的 IP 开放，需要时去控制台改。或者上 VPN / 堡垒机。

被锁在外面的自救（需要能 SSH）：

```bash
rm -f /www/server/panel/data/limitip.conf     # 清除 IP 限制
rm -f /www/server/panel/data/domain.conf      # 清除域名绑定
bt restart
```

### 4.4 改用户名密码

默认生成的用户名是随机的，但还是要改成你自己记得住又不好猜的。

```bash
bt 5            # 改密码
bt 6            # 改用户名
```

### 4.5 开二次验证

面板 → 设置 → 安全 → 开启 Google 二次验证（TOTP）。用任意 authenticator app 扫码。

**开之前把恢复方式想清楚**：手机丢了怎么办。命令行关闭：

```bash
bt 23           # 关闭二次验证（部分版本是别的编号，bt 看菜单）
```

### 4.6 面板本身上 HTTPS

面板 → 设置 → 面板 SSL。没有域名时用自签证书（浏览器会警告，但至少密码不是明文传的）。**有域名的话签个正式证书**，用免费的 Let's Encrypt 就行。

不开 SSL 的后果：你的面板密码在每次登录时以明文经过整条网络链路。

### 4.7 关掉不用的东西

- **卸载 phpMyAdmin**，如果你用 Navicat / TablePlus 走 SSH 隧道连数据库。留着就是一个已知的攻击面。
- 面板 → 软件商店，卸载所有没在用的插件。

必须留 phpMyAdmin 的话，至少：面板 → 软件商店 → phpMyAdmin → 设置，改访问端口并只允许你的 IP。

### 4.8 关掉面板的外网访问（最强方案）

如果你能熟练用 SSH，最安全的做法是**面板只监听 127.0.0.1，通过 SSH 隧道访问**：

```bash
# 本地机器上执行，把远程的 8888 映射到本地的 8888
ssh -N -L 8888:127.0.0.1:8888 root@your-server-ip

# 然后浏览器访问 http://127.0.0.1:8888/你的安全入口
```

这样面板端口在公网上根本不存在，扫描器扫不到。云安全组里把面板端口彻底关掉。

### 4.9 加固清单

```
□ 面板端口已改（非 8888）
□ 安全入口已开启且是随机串
□ 面板已绑定域名
□ 用户名密码已改，密码 ≥ 16 位随机
□ 已开二次验证
□ 面板已上 SSL
□ phpMyAdmin 已卸载，或已改端口 + 限 IP
□ 云安全组里面板端口只对固定 IP 开放
□ SSH 已改端口、已禁 root 密码登录（见第 19 节）
□ 已开启面板的登录告警通知
```

---

## 5. 宝塔到底改了你的系统什么

不理解这一节，你在网上搜到的任何标准 Linux 教程用在宝塔机器上都会对不上号——因为**宝塔把所有东西装到了 `/www/` 下，而不是系统标准位置**。

### 目录结构

```
/www/
├── server/                          # 所有软件的安装位置
│   ├── panel/                       # 宝塔面板本体
│   │   ├── data/                    # 面板配置（端口、入口、IP 限制都在这）
│   │   ├── vhost/                   # 站点配置文件的真正存放地
│   │   │   ├── nginx/               #   每个站一个 .conf
│   │   │   ├── rewrite/             #   伪静态规则
│   │   │   └── ssl/                 #   证书
│   │   ├── logs/                    # 面板自己的日志
│   │   ├── plugin/                  # 面板插件
│   │   └── class/                   # 面板的 Python 源码
│   ├── nginx/
│   │   ├── conf/nginx.conf          # ← Nginx 主配置（不是 /etc/nginx/）
│   │   ├── sbin/nginx               # ← 二进制
│   │   └── logs/
│   ├── php/
│   │   ├── 82/                      # PHP 8.2
│   │   │   ├── etc/php.ini          # ← PHP 配置
│   │   │   ├── etc/php-fpm.conf     # ← FPM 配置
│   │   │   ├── etc/php-fpm.d/       #   进程池配置
│   │   │   └── bin/php              # ← CLI 二进制
│   │   └── 81/                      # PHP 8.1（多版本共存）
│   ├── mysql/
│   │   ├── my.cnf -> /etc/my.cnf    # ← MySQL 配置（软链到 /etc）
│   │   └── data/                    # ← 数据文件
│   ├── redis/
│   ├── cron/                        # 面板管理的定时任务脚本
│   └── total/                       # 网站监控报表数据
│
├── wwwroot/                         # ★ 所有站点的网站根目录
│   ├── example.com/                 #   一个站一个目录
│   │   ├── wp-config.php
│   │   ├── wp-content/
│   │   └── .user.ini                # ← 宝塔生成的 open_basedir 限制
│   └── another.com/
│
├── wwwlogs/                         # ★ 所有站点的访问/错误日志
│   ├── example.com.log              #   访问日志
│   ├── example.com.error.log        #   错误日志
│   ├── nginx_error.log              #   Nginx 全局错误日志
│   └── php-82.slow.log              #   PHP 慢日志
│
└── backup/                          # 备份文件默认位置
    ├── database/
    └── site/
```

### 和标准位置的对照

网上教程说的 → 宝塔上实际在哪：

| 标准路径 | 宝塔路径 |
|---|---|
| `/etc/nginx/nginx.conf` | `/www/server/nginx/conf/nginx.conf` |
| `/etc/nginx/sites-available/` | `/www/server/panel/vhost/nginx/` |
| `/etc/php/8.2/fpm/php.ini` | `/www/server/php/82/etc/php.ini` |
| `/etc/php/8.2/fpm/pool.d/www.conf` | `/www/server/php/82/etc/php-fpm.d/www.conf` |
| `/etc/mysql/my.cnf` | `/etc/my.cnf`（这个宝塔用了标准位置） |
| `/var/www/html/` | `/www/wwwroot/站点域名/` |
| `/var/log/nginx/` | `/www/wwwlogs/` |
| `/usr/bin/php` | `/www/server/php/82/bin/php` |

### 服务怎么管

宝塔用自己的启动脚本，不完全走 systemd：

```bash
# 宝塔的方式（推荐，面板状态能同步）
/etc/init.d/nginx  {start|stop|restart|reload|status}
/etc/init.d/php-fpm-82  {start|stop|restart|reload}
/etc/init.d/mysqld  {start|stop|restart|status}
/etc/init.d/redis  {start|stop|restart}

# 面板自己
bt start / bt stop / bt restart / bt reload

# systemd 也能用（宝塔装了对应的 unit）
systemctl status nginx
systemctl restart php-fpm-82
```

> ⚠️ **PHP-FPM 的服务名带版本号**：`php-fpm-82`、`php-fpm-81`。多版本共存时它们是**各自独立的服务**，重启一个不影响另一个。这也意味着「改了 php.ini 没生效」经常是因为重启错了版本。

### php 命令指向的是哪个

宝塔机器上直接敲 `php -v` 可能报「command not found」，或者指向一个你没预期的版本。

```bash
which php                                # 看当前指向
ls /www/server/php/                       # 看装了哪些版本

# 明确指定版本（脚本里一律这么写）
/www/server/php/82/bin/php -v
/www/server/php/82/bin/php /www/wwwroot/example.com/wp-cli.phar --info
```

做个软链省事：

```bash
ln -sf /www/server/php/82/bin/php /usr/bin/php
```

但**定时任务和部署脚本里仍然要写全路径**——软链会在你切 PHP 版本时指向错误的地方，而 cron 报错是没人看的。

### .user.ini：宝塔特有的一个坑

宝塔会在每个站点根目录生成 `.user.ini`：

```ini
open_basedir=/www/wwwroot/example.com/:/tmp/
```

作用是把这个站的 PHP 限制在自己的目录里，**站点之间无法互相读文件**——这是好事，多站点隔离的关键。

但它会造成几个经典问题：

1. **站点无法访问 `/www/wwwroot/` 之外的路径**。比如你想让 WordPress 写到 `/data/uploads/`，会报 `open_basedir restriction in effect`。
2. **这个文件带 `i` 属性（immutable），普通的 `rm` 删不掉**：

```bash
lsattr /www/wwwroot/example.com/.user.ini      # 会看到 ----i---------
chattr -i /www/wwwroot/example.com/.user.ini   # 先去掉 immutable
vim /www/wwwroot/example.com/.user.ini         # 改
chattr +i /www/wwwroot/example.com/.user.ini   # 改完加回去
```

3. **迁移站点时把 `.user.ini` 一起拷过去了**，里面的路径还是老服务器的，新站直接 500。**迁移时一定要排除这个文件**。

4. 改了 `.user.ini` 之后**不是立刻生效**，PHP 有个缓存周期（`user_ini.cache_ttl`，默认 300 秒）。急的话重启 php-fpm。

---
## 6. Nginx：原理与配置结构

### 进程模型

```
nginx: master process        ← root 启动，只负责读配置、管理 worker、绑端口
 ├─ nginx: worker process    ← 用户 www，真正处理请求
 ├─ nginx: worker process
 ├─ nginx: worker process
 └─ nginx: cache manager     ← 如果开了 proxy/fastcgi cache
```

每个 worker 是**单线程 + 事件循环（epoll）**，一个 worker 可以同时持有几万个连接。所以：

- `worker_processes` 设成 **CPU 核数**（或 `auto`），设更多没有收益，反而增加上下文切换
- 这和 PHP-FPM 完全不同——PHP-FPM 是「一个进程同时只能处理一个请求」，所以它的进程数要大得多

**理论最大并发连接数** = `worker_processes × worker_connections`。注意每个反代/FastCGI 请求要占**两个**连接（一个对浏览器，一个对后端）。

### 配置文件的组织

```
/www/server/nginx/conf/nginx.conf              ← 主配置
  │
  ├── events { }                                ← 连接处理
  └── http {
        ...全局 http 设置...
        include /www/server/panel/vhost/nginx/*.conf;   ← ★ 站点配置在这里被引入
      }

/www/server/panel/vhost/nginx/example.com.conf  ← 单个站点（面板生成）
  └── server {
        listen 80;
        server_name example.com;
        root /www/wwwroot/example.com;
        include /www/server/panel/vhost/rewrite/example.com.conf;   ← 伪静态
        ...
      }
```

**改哪个文件**：

- 全站通用的（gzip、超时、缓冲区）→ 主配置 `nginx.conf` 的 `http` 块
- 单个站点的（伪静态、缓存头、防盗链）→ `vhost/nginx/站点.conf`
- 伪静态规则 → 面板里的「伪静态」框，对应 `vhost/rewrite/站点.conf`

> ⚠️ **面板会覆写 vhost 文件**。在面板里改站点设置（改 PHP 版本、加 SSL、改域名）时，宝塔会重新生成整个 vhost 文件，你手写进去的东西可能丢失。
>
> **保命做法**：自定义配置写进单独文件，在 vhost 里 `include` 进来。面板重写 vhost 时通常保留 `#PROXY-START/END`、`#SSL-START/END` 这类标记块之外的内容，但不保险。更稳的是把自定义规则放到伪静态框里（那个框的内容宝塔不动）。

### 主配置的关键指令

```nginx
user  www www;
worker_processes  auto;                    # = CPU 核数
worker_rlimit_nofile 65535;                # 每个 worker 能开的 fd 数

events {
    use epoll;                             # Linux 上的高效事件模型
    worker_connections 51200;              # 单 worker 最大连接数
    multi_accept on;                       # 一次事件循环接受多个新连接
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    # ---- 传输优化 ----
    sendfile        on;                    # 零拷贝：磁盘→网卡不过用户态
    tcp_nopush      on;                    # 攒满一个包再发（配合 sendfile）
    tcp_nodelay     on;                    # 但不为了攒包而延迟（长连接上）
    keepalive_timeout 60;
    keepalive_requests 1000;               # 一个长连接最多处理多少请求

    # ---- 上传体积 ----
    client_max_body_size 100m;             # ★ 决定 WP 后台能传多大的文件
    client_body_buffer_size 512k;
    client_header_timeout 60;
    client_body_timeout 60;

    # ---- 隐藏版本号 ----
    server_tokens off;                     # 响应头不再暴露 nginx/1.24.0

    # ---- 压缩 ----
    gzip on;
    gzip_min_length 1k;                    # 太小的文件压了反而更大
    gzip_comp_level 4;                     # 1-9，4~6 是性价比区间
    gzip_types text/plain text/css text/javascript
               application/json application/javascript
               application/xml application/xml+rss
               image/svg+xml font/ttf font/otf;
    gzip_vary on;                          # 加 Vary: Accept-Encoding，CDN 必需
    gzip_proxied any;
    # 注意：不要压缩 jpg/png/webp/mp4，它们已经是压缩格式，压了只浪费 CPU

    # ---- FastCGI ----
    fastcgi_connect_timeout 300;
    fastcgi_send_timeout 300;
    fastcgi_read_timeout 300;              # ★ 504 就是这个超时了
    fastcgi_buffer_size 64k;
    fastcgi_buffers 4 64k;                 # ★ 太小会报 "upstream sent too big header"
    fastcgi_busy_buffers_size 128k;
    fastcgi_temp_file_write_size 256k;
    fastcgi_intercept_errors on;

    # ---- 日志 ----
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for" '
                    'rt=$request_time urt=$upstream_response_time';
    access_log /www/wwwlogs/access.log main;

    include /www/server/panel/vhost/nginx/*.conf;
}
```

**`rt=$request_time urt=$upstream_response_time` 这两个变量强烈建议加上**——`request_time` 是整个请求耗时，`upstream_response_time` 是 PHP 那部分耗时。两者相减就是 Nginx 自己和网络的开销。排查「网站慢」时这是最直接的数据。

### location 匹配优先级

这是 Nginx 配置里最容易搞错的地方。匹配顺序**不是从上到下**：

```
1. =         精确匹配，命中立刻停止
2. ^~        前缀匹配，命中后不再尝试正则
3. ~  / ~*   正则匹配（~ 区分大小写，~* 不区分），按配置文件里的顺序，第一个命中就停
4. /path     普通前缀匹配，记录最长的那个，但会继续尝试正则
5. /         兜底
```

一个典型的踩坑：

```nginx
location /uploads/ {                    # 普通前缀匹配
    expires 30d;
}
location ~ \.php$ {                     # 正则匹配
    fastcgi_pass ...;
}
```

访问 `/uploads/evil.php` 时，**正则会赢**，PHP 被执行了。想让上传目录彻底不执行 PHP，必须用 `^~`：

```nginx
location ^~ /wp-content/uploads/ {
    location ~ \.php$ { deny all; }     # 嵌套一层保险
    expires 30d;
}
```

### 改完配置怎么生效

```bash
nginx -t                          # ★ 先测语法，绝对不要跳过这步
nginx -s reload                   # 平滑重载：老 worker 处理完手上的请求再退出，零中断
/etc/init.d/nginx reload          # 宝塔的方式，等价

# reload 不够用的情况（改了 user / worker_processes / 监听端口）
/etc/init.d/nginx restart         # 会有短暂中断
```

**`nginx -t` 通过但 `reload` 后没生效**，通常是三种情况：

1. 改错文件了（改了 `nginx.conf` 但站点配置里有同名指令覆盖）
2. 有多个 server 块 `server_name` 冲突，请求被前一个接走了
3. 浏览器缓存 / CDN 缓存

排查用：

```bash
nginx -T | grep -n "你改的指令"      # -T 打印最终合并后的完整配置
```

`nginx -T` 是排查配置问题的终极手段——它输出的是 Nginx 实际看到的配置，所有 include 都展开了。

---

## 7. Nginx + WordPress 实战配置

### 7.1 WordPress 伪静态

这是**必须配**的。没有它，除了首页以外所有页面都是 404。

宝塔面板里：站点 → 设置 → 伪静态 → 选 `wordpress`。对应的规则是：

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

**这三个参数的含义**：

- `$uri` — 先看有没有这个实体文件（`/wp-content/themes/x/style.css`）
- `$uri/` — 再看有没有这个目录（会触发 `index` 指令）
- `/index.php?$args` — 都没有，交给 WordPress 的路由，`$args` 是原始查询串

**顺序不能变**。把 `/index.php` 放前面会导致所有静态文件都走 PHP。

多站点（子目录模式）的规则不一样：

```nginx
# WordPress Multisite - 子目录模式
if (!-e $request_filename) {
    rewrite /wp-admin$ $scheme://$host$uri/ permanent;
    rewrite ^/[_0-9a-zA-Z-]+(/wp-.*) $1 last;
    rewrite ^/[_0-9a-zA-Z-]+(/.*\.php)$ $1 last;
}
try_files $uri $uri/ /index.php?$args;
```

### 7.2 完整的站点配置

这是一份可以直接用的 WordPress 站点配置，逐段带注释：

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;
    root /www/wwwroot/example.com;
    index index.php index.html;

    # ---- 强制 HTTPS（有证书后打开）----
    # return 301 https://$host$request_uri;

    # ---- 日志 ----
    access_log /www/wwwlogs/example.com.log main;
    error_log  /www/wwwlogs/example.com.error.log;

    # ---- 上传体积（会被 PHP 的 upload_max_filesize 再限一次，取小者）----
    client_max_body_size 128m;

    # ---- WordPress 伪静态 ----
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # ---- PHP 处理 ----
    location ~ [^/]\.php(/|$) {
        # ★ 关键：防止 /uploads/evil.jpg/x.php 这类路径穿透攻击
        fastcgi_split_path_info ^(.+?\.php)(/.*)$;
        if (!-f $document_root$fastcgi_script_name) {
            return 404;
        }

        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO       $fastcgi_path_info;
        fastcgi_param HTTPS           $https if_not_empty;
    }

    # ---- 静态资源长缓存 ----
    location ~* \.(jpg|jpeg|png|gif|webp|avif|ico|svg|css|js|woff2?|ttf|otf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;                      # 静态文件不写访问日志，省 IO
        log_not_found off;
    }

    # ---- 上传目录：绝不执行 PHP ----
    location ^~ /wp-content/uploads/ {
        location ~ \.(php|phtml|php[0-9]|phps|pht|shtml)$ {
            deny all;
        }
        expires 30d;
        access_log off;
    }

    # ---- 屏蔽敏感文件 ----
    location ~ /\. {                         # 所有点开头的（.git .env .user.ini）
        deny all;
        access_log off;
        log_not_found off;
    }
    location = /wp-config.php        { deny all; }
    location = /readme.html          { deny all; }
    location = /license.txt          { deny all; }
    location = /wp-config-sample.php { deny all; }
    location ~* /(?:wp-content|wp-includes)/.*\.(?:sql|bak|log|tar|gz|zip)$ { deny all; }

    # ---- xmlrpc：绝大多数站点用不到，且是暴力破解和 DDoS 放大的入口 ----
    location = /xmlrpc.php {
        deny all;
        access_log off;
    }
    # 如果你用 Jetpack 或 WP 手机 App，改成白名单：
    # location = /xmlrpc.php {
    #     allow 192.0.64.0/18;     # Jetpack 的段
    #     deny all;
    #     fastcgi_pass unix:/tmp/php-cgi-82.sock;
    #     include fastcgi_params;
    #     fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    # }

    # ---- 登录页限速（配合 http 块里的 limit_req_zone）----
    location = /wp-login.php {
        limit_req zone=wplogin burst=3 nodelay;
        fastcgi_pass unix:/tmp/php-cgi-82.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # ---- robots / favicon 不写日志 ----
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; allow all; }
}
```

配套的限速区（放在 `nginx.conf` 的 `http` 块里）：

```nginx
# 10m 的共享内存约能存 16 万个 IP；每 IP 每分钟 30 次登录尝试
limit_req_zone $binary_remote_addr zone=wplogin:10m rate=30r/m;
```

### 7.3 `fastcgi_split_path_info` 那一段为什么必须有

不加这段守卫时，请求 `/wp-content/uploads/2024/photo.jpg/hack.php`：

1. `location ~ \.php$` 匹配上了（URL 以 `.php` 结尾）
2. `SCRIPT_FILENAME` 被设成 `/www/wwwroot/x/wp-content/uploads/2024/photo.jpg/hack.php`
3. 这个文件不存在，但**老版本 PHP 在 `cgi.fix_pathinfo=1` 时会向上回溯**，找到 `photo.jpg` 并**把它当 PHP 执行**

攻击者只要能上传一张「图片」（内容是 PHP 代码），就能拿到 webshell。

`if (!-f $document_root$fastcgi_script_name) { return 404; }` 这一行把它堵死了。另外确认 `php.ini` 里：

```ini
cgi.fix_pathinfo = 0
```

宝塔的默认配置已经处理了这个，但**从老服务器迁过来的自定义配置经常没有**。

### 7.4 HTTP 到 HTTPS 的跳转

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name example.com www.example.com;
    return 301 https://$host$request_uri;
}
```

用 `return` 不要用 `rewrite`——`return` 不需要正则引擎，快得多。

**`$host` 还是 `$server_name`**：用 `$host`（请求头里的实际域名），这样 `example.com` 和 `www.example.com` 都能各跳各的，不会强制并到一个。要强制并到 www 或 non-www，再单独写一个 server 块。

### 7.5 在 CDN / 反代后面

站点前面挂了 CDN（Cloudflare、又拍云）或负载均衡时，`$remote_addr` 拿到的是 CDN 的 IP，**所有访客在日志里都是同一个 IP**，WordPress 的评论、登录限制、地理定位全部失效。

```nginx
# 在 http 块里
set_real_ip_from 173.245.48.0/20;      # CDN 的回源 IP 段，要填全
set_real_ip_from 103.21.244.0/22;
# ... Cloudflare 的完整列表：https://www.cloudflare.com/ips/
real_ip_header CF-Connecting-IP;        # Cloudflare 用这个头
# real_ip_header X-Forwarded-For;       # 通用的用这个
real_ip_recursive on;
```

同时 WordPress 那边也要认 HTTPS，否则会出现无限重定向（Nginx 说是 HTTP，WP 强制跳 HTTPS，CDN 又回源成 HTTP……）。在 `wp-config.php` 里，**放在 `require_once ABSPATH . 'wp-settings.php';` 之前**：

```php
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
     && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] ) {
    $_SERVER['HTTPS'] = 'on';
}
```

### 7.6 大文件上传

WordPress 后台传不了大文件时，有**四个**地方在限制，取最小值：

| 位置 | 参数 | 宝塔在哪改 |
|---|---|---|
| Nginx | `client_max_body_size` | 站点设置 → 配置文件，或主配置 |
| PHP | `upload_max_filesize` | 软件商店 → PHP → 设置 → 配置修改 |
| PHP | `post_max_size` | 同上，**必须 ≥ upload_max_filesize** |
| PHP | `max_execution_time` / `max_input_time` | 同上，大文件慢网速会超时 |

四个都要改。只改一个的话，症状是「传到一半失败」或「HTTP error」而没有明确报错。

```ini
upload_max_filesize = 128M
post_max_size = 128M
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
```

改完重启 PHP-FPM。用 `phpinfo()` 或 WordPress 后台的「站点健康」确认生效。

---
## 8. PHP-FPM：进程模型与调优

### 为什么它和 Nginx 完全不同

**一个 PHP-FPM worker 同一时刻只能处理一个请求。** 没有事件循环，没有协程，一个请求跑完（包括等数据库、等外部 API）才能接下一个。

所以：

- 并发能力 = `pm.max_children`，一个都不能多
- worker 被打满 = Nginx 拿不到后端 = **502**
- 一个卡住的请求（比如调用外部支付接口超时 30 秒）会**占着一个 worker 整整 30 秒**

WordPress 站点的 502，八成是 `pm.max_children` 不够或者有慢请求堆积。

### 配置文件

```
/www/server/php/82/etc/php-fpm.conf          # 全局
/www/server/php/82/etc/php-fpm.d/www.conf    # 进程池（主要改这个）
/www/server/php/82/etc/php.ini               # PHP 语言层配置
```

面板路径：软件商店 → PHP 8.2 → 设置 →「性能调整」/「配置修改」。

### 进程管理模式

```ini
[www]
user = www
group = www
listen = /tmp/php-cgi-82.sock
listen.owner = www
listen.group = www
listen.mode = 0666
listen.backlog = 8192          # 等待队列，满了就直接 502

pm = dynamic
pm.max_children = 40           # ★ 最重要的参数：并发上限
pm.start_servers = 10          # 启动时拉起几个
pm.min_spare_servers = 5       # 空闲进程下限
pm.max_spare_servers = 20      # 空闲进程上限
pm.max_requests = 1000         # ★ 处理多少请求后重启该进程（防内存泄漏）
pm.process_idle_timeout = 10s

request_terminate_timeout = 300  # 超过就杀掉，防止卡死的请求占着 worker
slowlog = /www/wwwlogs/php-82-slow.log
request_slowlog_timeout = 5     # ★ 超过 5 秒的请求打堆栈到慢日志
```

**三种 `pm` 模式**：

| 模式 | 行为 | 适用 |
|---|---|---|
| `static` | 固定开 `max_children` 个，不增不减 | 内存充裕、流量稳定的专用机。响应最快，没有 fork 开销 |
| `dynamic` | 在 min/max spare 之间动态增减 | **默认选它**，兼顾内存和响应 |
| `ondemand` | 平时 0 个进程，来请求才 fork | 内存极紧张、或一台机器跑很多低流量站点。首次请求会慢 |

### pm.max_children 怎么算

```
pm.max_children = (可用内存 - 系统 - MySQL - Nginx - Redis) / 单进程内存
```

先量单进程实际占多少：

```bash
# 看每个 php-fpm 进程的 RSS（单位 KB）
ps -ylC php-fpm --sort:rss | awk '{print $8/1024 " MB\t" $12}'

# 算平均值
ps --no-headers -o rss -C php-fpm | awk '{s+=$1; n++} END {print s/n/1024 " MB avg, " n " procs"}'
```

一台 4G 的机器，跑 WordPress + WooCommerce：

```
4096MB
- 400MB  系统 + 宝塔面板
- 1200MB MySQL (innodb_buffer_pool 1G + 开销)
- 100MB  Nginx
- 200MB  Redis
= 2196MB 可用

单进程实测 100MB  →  pm.max_children = 2196 / 100 ≈ 21
保守取 20
```

> ⚠️ **宁可少不要多**。设太大的后果比设太小严重得多：设小了是请求排队（慢），设大了是内存耗尽 → OOM killer 杀掉 MySQL → **全站挂掉**。

### 怎么知道设小了

PHP-FPM 会在**自己的错误日志**里直说：

```bash
tail -f /www/server/php/82/var/log/php-fpm.log
```

```
WARNING: [pool www] server reached pm.max_children setting (20), consider raising it
```

看到这条就是不够了。但**先别急着调大**——先确认是「真的流量上来了」还是「有慢请求在堆积」。看慢日志：

```bash
tail -100 /www/wwwlogs/php-82-slow.log
```

慢日志会打出完整的 PHP 调用栈，直接告诉你卡在哪个函数：

```
[08-Sep-2026 10:23:45]  [pool www] pid 12345
script_filename = /www/wwwroot/example.com/index.php
[0x00007f...] curl_exec() /www/wwwroot/example.com/wp-content/plugins/some-plugin/api.php:88
[0x00007f...] fetch_remote_data() ...
```

这个例子里是插件在同步请求外部 API——调大 `max_children` 只会让更多进程一起卡住，正确做法是给那个 curl 加超时或改成异步。

**`request_slowlog_timeout` 是排查性能问题最有价值的一个开关**，生产环境常开，设 5 秒。

### 开启状态页

```ini
# www.conf
pm.status_path = /fpm-status
ping.path = /fpm-ping
```

```nginx
# 站点配置里，只对本机开放
location = /fpm-status {
    allow 127.0.0.1;
    deny all;
    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

```bash
curl 'http://127.0.0.1/fpm-status?full'
```

```
pool:                 www
process manager:      dynamic
idle processes:       12
active processes:     8
total processes:      20
max active processes: 20        ← 曾经达到过上限
max children reached: 3         ← ★ 打满过 3 次，说明要调大了
slow requests:        17        ← ★ 有慢请求
```

**`max children reached` 不为 0 就要处理**。

### php.ini 关键项

```ini
memory_limit = 256M              # WooCommerce / 页面构建器建议 512M
max_execution_time = 300         # CLI 下无限制，只影响 Web 请求
max_input_vars = 3000            # ★ 菜单项/ACF 字段多时必须调大，否则后台保存丢数据
post_max_size = 128M
upload_max_filesize = 128M
max_file_uploads = 50

date.timezone = Asia/Shanghai
cgi.fix_pathinfo = 0             # ★ 安全，见 7.3
expose_php = Off                 # 响应头不暴露 PHP 版本

# 生产环境
display_errors = Off             # ★ 绝不能 On，会泄露路径和数据库信息
display_startup_errors = Off
log_errors = On
error_log = /www/wwwlogs/php_error.log
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT

# 禁用危险函数（宝塔默认已禁一部分）
disable_functions = passthru,exec,system,chroot,chgrp,chown,shell_exec,proc_open,proc_get_status,popen,ini_alter,ini_restore,dl,openlog,syslog,readlink,symlink,popepassthru,pcntl_alarm,pcntl_fork,pcntl_waitpid,pcntl_wait,pcntl_exec
```

> ⚠️ `max_input_vars` 是个隐形杀手。WordPress 后台保存一个有 200 个菜单项的菜单，或者保存一个 ACF 灵活内容页面，POST 的字段数轻松过 1000。超过 `max_input_vars` 时 **PHP 静默丢弃多余字段，不报错**——表现是「保存后一部分内容没了」。改成 3000 或 5000。

### `disable_functions` 和 WordPress 的冲突

禁用 `proc_open` / `popen` 会让一部分插件报错。常见的：

- **WP-CLI** 需要 `proc_open`
- 某些备份插件需要 `shell_exec` 调 `mysqldump`
- ImageMagick 的部分调用路径需要 `exec`

真遇到冲突时，**优先换一个不需要这些函数的插件**，而不是解除禁用。实在要解除，只解除必需的那一个，不要整行清空。

### 多 PHP 版本

宝塔可以同时装多个 PHP，每个站点在「站点设置 → PHP 版本」里单独选。

```
/tmp/php-cgi-74.sock     ← PHP 7.4
/tmp/php-cgi-81.sock     ← PHP 8.1
/tmp/php-cgi-82.sock     ← PHP 8.2
```

站点配置里的 `fastcgi_pass` 指向哪个 socket，就用哪个版本。**升级 PHP 版本的安全流程**：

1. 装好新版本，装齐扩展（对着老版本的 `php -m` 输出装）
2. 克隆一个测试站，切到新版本
3. 跑一遍关键流程（下单、支付、表单、后台保存）
4. 生产站切换，**盯着 `php_error.log` 看 30 分钟**
5. 出问题就在面板里切回去（改一下 socket，reload nginx，秒级回滚）

---

## 9. MySQL / MariaDB：配置与调优

### 配置文件

```
/etc/my.cnf                      ← 宝塔用的是标准位置
/www/server/mysql/data/          ← 数据文件
```

面板：软件商店 → MySQL → 设置 →「性能调整」/「配置修改」。

### 最重要的一个参数

```ini
[mysqld]
innodb_buffer_pool_size = 1G     # ★★★
```

**这一个参数决定 80% 的数据库性能。** 它是 InnoDB 用来缓存数据页和索引页的内存池——命中缓存就是内存读（纳秒级），不命中就是磁盘读（毫秒级），差三个数量级。

设置原则：

| 场景 | 值 |
|---|---|
| 数据库专用机 | 物理内存的 **70–80%** |
| **和 PHP/Nginx 共用一台**（我们的情况） | 物理内存的 **25–40%** |
| 数据总量小于这个值 | 设成「数据总量 × 1.2」就够，多了浪费 |

先看数据到底多大：

```sql
SELECT
  table_schema AS db,
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
FROM information_schema.tables
GROUP BY table_schema
ORDER BY size_mb DESC;
```

一个普通 WordPress 站的数据库通常只有 50–300MB，一个大 WooCommerce 站可能到 2–5GB。

**检查命中率**（应该 > 99%）：

```sql
SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_read%';
```

```
Innodb_buffer_pool_read_requests   ← 总的逻辑读
Innodb_buffer_pool_reads           ← 不得不去磁盘读的次数

命中率 = 1 - reads / read_requests
```

命中率低于 95% 就该加大 buffer pool（前提是内存还有）。

### 完整配置模板（4G 内存，WordPress + WooCommerce）

```ini
[mysqld]
# ---- 基础 ----
port = 3306
datadir = /www/server/mysql/data
socket = /tmp/mysql.sock
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
skip-name-resolve                    # ★ 不做反向 DNS 解析，能显著减少连接延迟
                                     #   注意：开了之后授权只能用 IP 不能用主机名

# ---- InnoDB ----
default_storage_engine = InnoDB
innodb_buffer_pool_size = 1G         # ★ 见上文
innodb_buffer_pool_instances = 1     # buffer pool > 1G 时设成 (size/1G)
innodb_log_file_size = 256M          # 大 = 写入快、崩溃恢复慢。取 buffer_pool 的 1/4
innodb_log_buffer_size = 16M
innodb_flush_log_at_trx_commit = 2   # ★ 见下方说明
innodb_flush_method = O_DIRECT       # 绕过系统页缓存，避免双重缓存
innodb_file_per_table = 1            # 每表一个 .ibd 文件，方便单表恢复和空间回收
innodb_io_capacity = 2000            # SSD 用 2000，NVMe 用 4000+，机械盘用 200
innodb_read_io_threads = 4
innodb_write_io_threads = 4

# ---- 连接 ----
max_connections = 200                # ★ 见下方说明
max_connect_errors = 1000
wait_timeout = 300                   # 空闲连接多久回收，默认 28800 太长
interactive_timeout = 300
max_allowed_packet = 64M             # 导入大 SQL 或存大 post_content 时需要

# ---- 缓冲区（每连接分配，不要设太大）----
sort_buffer_size = 2M
read_buffer_size = 2M
read_rnd_buffer_size = 4M
join_buffer_size = 4M
tmp_table_size = 64M
max_heap_table_size = 64M            # ★ 必须和 tmp_table_size 一致

# ---- 表缓存 ----
table_open_cache = 4000
table_definition_cache = 2000
open_files_limit = 65535

# ---- 慢查询日志 ----
slow_query_log = 1
slow_query_log_file = /www/server/mysql/mysql-slow.log
long_query_time = 1                  # ★ 超过 1 秒的记下来
log_queries_not_using_indexes = 0    # 生产环境关掉，否则日志爆炸

# ---- binlog ----
# 不做主从复制的话可以关掉，能省不少磁盘 IO 和空间
# 但关掉就没法做时间点恢复，权衡
server-id = 1
log-bin = mysql-bin
binlog_format = ROW
binlog_expire_logs_seconds = 604800  # 7 天（MySQL 8.0）
# expire_logs_days = 7               # MySQL 5.7 用这个
max_binlog_size = 256M

[client]
socket = /tmp/mysql.sock
default-character-set = utf8mb4

[mysqldump]
quick
max_allowed_packet = 64M
```

### `innodb_flush_log_at_trx_commit` 的三个值

这个参数是**性能和数据安全的直接权衡**：

| 值 | 行为 | 断电时最多丢 | 性能 |
|---|---|---|---|
| `1` | 每次事务提交都 fsync 到磁盘 | 0 | 最慢（每次提交一次磁盘同步） |
| `2` | 每次提交写到 OS 缓存，每秒 fsync | **1 秒的事务** | 快很多 |
| `0` | 每秒才写一次 log buffer | 1 秒 | 最快，但 MySQL 崩溃也会丢 |

**普通 WordPress 站点用 `2`**。丢 1 秒的数据意味着最多丢一条评论或一次自动保存，换来的写入性能提升是数倍。

**做支付、做订单的站点用 `1`**。丢一笔订单的代价远大于性能。

### `max_connections` 怎么算

```
max_connections ≥ pm.max_children + WP-CLI/cron 的连接 + 你自己的连接 + 余量
```

PHP-FPM 每个 worker 处理请求时会开 1 个数据库连接（WordPress 不用连接池）。所以 `max_children = 20` 时，理论峰值就是 20 个连接。

设 `200` 是很宽裕的。**但不要设成几千**——每个连接都要分配 `sort_buffer_size + read_buffer_size + join_buffer_size` 等，几千个连接同时活跃会瞬间吃光内存。

看实际用到多少：

```sql
SHOW GLOBAL STATUS LIKE 'Max_used_connections';      -- 历史峰值
SHOW GLOBAL STATUS LIKE 'Threads_connected';         -- 当前
SHOW GLOBAL STATUS LIKE 'Aborted_connects';          -- 失败的连接数
```

`Max_used_connections` 长期贴着 `max_connections`，说明要么调大，要么有连接泄漏。

### 慢查询分析

```bash
# 直接看
tail -50 /www/server/mysql/mysql-slow.log

# 用 mysqldumpslow 聚合（按总耗时排序，取前 10）
mysqldumpslow -s t -t 10 /www/server/mysql/mysql-slow.log

# 更好用的是 percona-toolkit 的 pt-query-digest
apt install percona-toolkit
pt-query-digest /www/server/mysql/mysql-slow.log > /tmp/slow-report.txt
```

`pt-query-digest` 会输出一张按「总耗时占比」排序的表，直接告诉你哪条 SQL 最值得优化。

**WordPress 站点最常见的慢查询**：

1. **`wp_options` 的 autoload 查询**。这条查询每个请求都跑：

```sql
SELECT option_name, option_value FROM wp_options WHERE autoload = 'yes';
```

插件把大量数据写成 `autoload=yes` 的 option 时，这条会从几 KB 涨到几 MB。查一下：

```sql
SELECT ROUND(SUM(LENGTH(option_value))/1024/1024, 2) AS mb
FROM wp_options WHERE autoload = 'yes';

-- 找出最大的几条
SELECT option_name, ROUND(LENGTH(option_value)/1024, 1) AS kb
FROM wp_options WHERE autoload = 'yes'
ORDER BY LENGTH(option_value) DESC LIMIT 20;
```

**超过 1MB 就该处理了**。通常是卸载的插件留下的垃圾，或者某个插件把日志写进了 options。确认无用后：

```sql
UPDATE wp_options SET autoload = 'no' WHERE option_name = 'xxx';
```

2. **`wp_postmeta` 上的 meta_query**。这张表在 WooCommerce 站点上能有几百万行。确认索引在：

```sql
SHOW INDEX FROM wp_postmeta;
-- 应该有 post_id 和 meta_key 的索引
```

WooCommerce 的解法是「查找表 + HPOS」，见 `woocommerce.md` 第 2 节。

3. **`wp_posts` 的 `post_status + post_type` 组合查询**没走索引。WordPress 自带 `type_status_date` 索引，如果被某个插件删了要加回来。

### 表维护

```bash
# 检查 + 优化所有表（会锁表，在低峰期做）
mysqlcheck -u root -p --auto-repair --optimize --all-databases

# 只优化某个库
mysqlcheck -u root -p --optimize wordpress_db
```

`OPTIMIZE TABLE` 对 InnoDB 的作用是重建表、回收碎片空间。**删了大量数据（比如清理了 10 万条 revision）之后做一次**，平时不用做。

清理 WordPress 冗余数据（做之前先备份）：

```sql
-- 文章修订版本（大站上这个能占一半空间）
DELETE FROM wp_posts WHERE post_type = 'revision';

-- 孤立的 postmeta
DELETE pm FROM wp_postmeta pm
LEFT JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.ID IS NULL;

-- 垃圾评论
DELETE FROM wp_comments WHERE comment_approved = 'spam';

-- 孤立的 commentmeta
DELETE cm FROM wp_commentmeta cm
LEFT JOIN wp_comments c ON c.comment_ID = cm.comment_id
WHERE c.comment_ID IS NULL;

-- 过期 transient
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_%'
  AND option_value < UNIX_TIMESTAMP();
```

用 WP-CLI 更安全（它会走 WordPress 的 API，触发正确的钩子）：

```bash
wp post delete $(wp post list --post_type=revision --format=ids) --force
wp transient delete --expired
wp db optimize
```

限制修订版本数量，从源头解决（`wp-config.php`）：

```php
define( 'WP_POST_REVISIONS', 5 );      // 每篇最多留 5 个
define( 'AUTOSAVE_INTERVAL', 300 );    // 自动保存间隔改成 5 分钟
define( 'EMPTY_TRASH_DAYS', 7 );       // 回收站 7 天自动清空
```

---
## 10. 建站完整流程

从零到一个能访问的 WordPress 站点，完整顺序。

### 10.1 域名解析

先做 DNS，因为生效需要时间（几分钟到几小时），后面申请 SSL 证书要用。

在域名服务商处添加：

| 类型 | 主机记录 | 值 |
|---|---|---|
| A | `@` | 服务器公网 IP |
| A | `www` | 服务器公网 IP |

验证：

```bash
dig +short example.com
dig +short www.example.com
nslookup example.com 8.8.8.8        # 用公共 DNS 查，绕过本地缓存
```

**必须等到解析结果是你的服务器 IP 再往下走**。没解析好就去申请 Let's Encrypt 证书，一定失败，而且失败次数多了会被限流（每个域名每小时 5 次）。

### 10.2 面板建站

面板 → 网站 → 添加站点：

| 字段 | 填什么 |
|---|---|
| 域名 | `example.com` 和 `www.example.com` 各一行 |
| 根目录 | 默认 `/www/wwwroot/example.com` |
| FTP | **不创建**（FTP 是明文协议，用 SFTP 代替） |
| 数据库 | 创建，字符集选 `utf8mb4` |
| PHP 版本 | 8.2 |

**数据库密码让面板随机生成**，然后立刻记下来。

宝塔这一步实际做了：

```
1. mkdir /www/wwwroot/example.com
2. 生成 /www/server/panel/vhost/nginx/example.com.conf
3. 生成 /www/wwwroot/example.com/.user.ini（open_basedir）
4. CREATE DATABASE example_com CHARACTER SET utf8mb4;
   CREATE USER 'example_com'@'localhost' IDENTIFIED BY '...';
   GRANT ALL ON example_com.* TO 'example_com'@'localhost';
5. chown -R www:www /www/wwwroot/example.com
6. nginx -s reload
```

### 10.3 装 WordPress

**推荐用 WP-CLI**，比面板的一键部署干净（面板的一键部署经常带一堆你不需要的插件）。

```bash
# 装 WP-CLI（一次性）
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
mv wp-cli.phar /usr/local/bin/wp

# 宝塔上 wp 命令默认找不到 php，做个包装
cat > /usr/local/bin/wp <<'WPEOF'
#!/bin/bash
/www/server/php/82/bin/php /usr/local/bin/wp-cli.phar --allow-root "$@"
WPEOF
chmod +x /usr/local/bin/wp
```

然后：

```bash
cd /www/wwwroot/example.com

# 下载核心（简体中文）
wp core download --locale=zh_CN

# 生成 wp-config.php
wp config create \
  --dbname=example_com \
  --dbuser=example_com \
  --dbpass='你的数据库密码' \
  --dbhost=localhost \
  --dbcharset=utf8mb4 \
  --dbcollate=utf8mb4_unicode_ci

# 安装
wp core install \
  --url=https://example.com \
  --title="站点标题" \
  --admin_user=不要用admin \
  --admin_password='强密码' \
  --admin_email=you@example.com \
  --skip-email

# 清掉默认内容
wp plugin delete hello akismet
wp post delete 1 --force            # "世界，你好！"
wp post delete 2 --force            # "示例页面"
wp theme delete twentytwentythree twentytwentytwo

# 设置固定链接（这一步很多人忘，忘了就是默认的 ?p=123）
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# 时区和格式
wp option update timezone_string 'Asia/Shanghai'
wp option update date_format 'Y-m-d'

# 关掉「阻止搜索引擎索引」（如果是生产站）
wp option update blog_public 1

# 修正属主（wp-cli 用 root 跑的，文件属主会是 root）
chown -R www:www /www/wwwroot/example.com
```

### 10.4 wp-config.php 的生产配置

```php
// ---- 调试：生产环境这样配 ----
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', true );        // 仍然记日志，但不显示给访客
define( 'WP_DEBUG_DISPLAY', false );   // ★ 生产环境必须 false
@ini_set( 'display_errors', 0 );

// ---- 安全 ----
define( 'DISALLOW_FILE_EDIT', true );  // ★ 关掉后台的主题/插件代码编辑器
define( 'DISALLOW_FILE_MODS', false ); // true 会连插件安装都禁掉，按需
define( 'FORCE_SSL_ADMIN', true );     // 后台强制 HTTPS

// ---- 性能 ----
define( 'WP_POST_REVISIONS', 5 );
define( 'AUTOSAVE_INTERVAL', 300 );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );   // 后台用的上限

// ---- 关掉 WP-Cron，改用系统 cron（见第 16 节）----
define( 'DISABLE_WP_CRON', true );

// ---- 固定域名，避免被 Host 头污染 ----
define( 'WP_HOME',    'https://example.com' );
define( 'WP_SITEURL', 'https://example.com' );

// ---- 自动更新：只收安全小版本 ----
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

**盐值**要重新生成（`wp config create` 会自动做，手动装的话去 https://api.wordpress.org/secret-key/1.1/salt/ 拿）。

### 10.5 上线前检查清单

```
□ 域名解析生效，www 和裸域都指向服务器
□ HTTPS 已配置，HTTP 自动跳转
□ 固定链接已设置且文章页能打开（不是 404）
□ wp-config.php 里 WP_DEBUG_DISPLAY = false
□ DISALLOW_FILE_EDIT = true
□ 管理员用户名不是 admin
□ 「设置 → 阅读 → 建议搜索引擎不索引本站点」已取消勾选
□ 上传目录不能执行 PHP（用一个 .php 文件实测）
□ 文件属主是 www:www
□ 数据库定时备份已配置且验证过能恢复
□ 邮件能发出（用 SMTP 插件，别指望 PHP mail()）
□ 站点健康（工具 → 站点健康）没有严重问题
```

**上传目录的 PHP 执行测试**，一定要实际做一遍：

```bash
echo '<?php echo "EXECUTED";' > /www/wwwroot/example.com/wp-content/uploads/t.php
curl https://example.com/wp-content/uploads/t.php
# 期望：403 或返回源码，绝不能返回 "EXECUTED"
rm /www/wwwroot/example.com/wp-content/uploads/t.php
```

---

## 11. HTTPS 与证书

### 面板申请 Let's Encrypt

站点 → 设置 → SSL → Let's Encrypt → 勾上所有域名 → 申请。

宝塔会自动：申请证书 → 写入 vhost 的 `#SSL-START/END` 块 → reload nginx → 建一个定时任务在到期前续期。

**Let's Encrypt 证书 90 天有效期，靠自动续期**。续期失败是最常见的「网站突然打不开」原因之一。

### 申请失败的排查

| 报错 | 原因 | 解决 |
|---|---|---|
| `DNS problem: NXDOMAIN` | 域名没解析 | 等解析生效，`dig` 确认 |
| `Invalid response ... 404` | 验证文件访问不到 | 见下方 |
| `Timeout during connect` | 80 端口没开 | 云安全组 + 系统防火墙都要放行 80 |
| `too many certificates already issued` | 触发限流 | 同一域名每周最多 5 张，等一周或用 staging 环境测试 |

**验证文件 404 是最常见的一个**。Let's Encrypt 的 HTTP-01 验证会访问：

```
http://example.com/.well-known/acme-challenge/随机串
```

但我们在第 7 节配的这条规则会把它挡掉：

```nginx
location ~ /\. { deny all; }        # ← .well-known 也是点开头的！
```

所以必须加例外，**放在 deny 规则之前**：

```nginx
location ^~ /.well-known/acme-challenge/ {
    allow all;
    default_type "text/plain";
    root /www/wwwroot/example.com;
}
location ~ /\. { deny all; }
```

用 `^~` 保证它优先于后面的正则。

另一个原因是 WordPress 的伪静态把请求转给了 `index.php`——WordPress 返回 404 页面，Let's Encrypt 拿到的不是验证串。上面那条 `^~` 规则同样解决这个。

### DNS 验证（没有 80 端口 / 泛域名）

需要泛域名证书（`*.example.com`）时**只能用 DNS-01 验证**。宝塔支持接入 DNS 服务商 API 自动完成，在 SSL 页面选「DNS 验证」并填 API key。

手动的话用 acme.sh：

```bash
curl https://get.acme.sh | sh
export CF_Token="你的Cloudflare API Token"
~/.acme.sh/acme.sh --issue --dns dns_cf -d example.com -d '*.example.com'
```

### 强制 HTTPS 与 HSTS

面板 SSL 页面有「强制 HTTPS」开关，等价于第 7.4 节那段 `return 301`。

HSTS（告诉浏览器「以后只准用 HTTPS 访问我」）：

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

> ⚠️ **HSTS 是不可逆的**。浏览器记住之后，在 `max-age` 期间内**无论如何都不会用 HTTP 访问你的域名**，即使你的证书过期了、即使你手动输 `http://`。证书出问题时，访客看到的是无法绕过的错误页。
>
> 上 HSTS 前先用小的 `max-age`（比如 300）跑一周，确认证书续期稳定，再加到一年。**不要随便提交 preload 列表**，那个移除要几个月。

`always` 参数很重要——没有它，Nginx 只在 2xx/3xx 响应上加这个头，4xx/5xx 就漏了。

### 完整的安全响应头

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

> ⚠️ **`add_header` 不会继承**：一旦某个 `location` 块里写了任何 `add_header`，父级的所有 `add_header` 在这个 location 里全部失效。所以静态资源那个 location 里如果加了 `Cache-Control`，就必须把安全头也重复一遍。
>
> 这是 Nginx 最反直觉的行为之一，也是「安全头在首页有、在图片上没有」的原因。

### SSL 参数调优

```nginx
ssl_protocols TLSv1.2 TLSv1.3;          # 1.0/1.1 已不安全，别开
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
ssl_prefer_server_ciphers off;          # TLS1.3 时代让客户端选更好
ssl_session_cache shared:SSL:10m;       # ★ 会话复用，省掉重复握手
ssl_session_timeout 1d;
ssl_session_tickets off;
ssl_stapling on;                        # OCSP 装订，减少一次客户端到 CA 的查询
ssl_stapling_verify on;
resolver 223.5.5.5 8.8.8.8 valid=300s;  # OCSP 需要 DNS
```

`ssl_session_cache` 效果明显——没有它，每个新连接都要完整 TLS 握手（2 个 RTT）。

去 https://www.ssllabs.com/ssltest/ 测一下，目标是 A 或 A+。

---

## 12. 缓存体系：OPcache / Redis / FastCGI Cache

WordPress 的性能优化是**分层的**，每一层解决不同的问题。按投入产出比排序：

| 层 | 缓存什么 | 省掉什么 | 收益 |
|---|---|---|---|
| **OPcache** | PHP 字节码 | 词法/语法分析、编译 | ⭐⭐⭐⭐⭐ 必开 |
| **对象缓存 (Redis)** | 数据库查询结果 | SQL 查询 | ⭐⭐⭐⭐ 强烈建议 |
| **页面缓存** | 完整 HTML | 整个 PHP + DB | ⭐⭐⭐⭐⭐ 但有适用限制 |
| **浏览器缓存** | 静态资源 | 网络往返 | ⭐⭐⭐ 配一下就行 |
| **CDN** | 静态资源 + 可选 HTML | 网络距离 | ⭐⭐⭐ 看用户地理分布 |

### 12.1 OPcache（必开）

PHP 是解释型语言，**每个请求都要把所有 .php 文件重新读盘、词法分析、语法分析、编译成字节码**。WordPress 一个请求要加载几百个文件。OPcache 把编译结果缓存在共享内存里，跳过全部这些步骤。

**开启 OPcache 通常能让 WordPress 快 2–3 倍**，而且零风险。

面板：软件商店 → PHP 8.2 → 安装扩展 → Zend OPcache，然后在「配置修改」里：

```ini
[opcache]
opcache.enable = 1
opcache.enable_cli = 0                 # CLI 用不上，反而每次都要建缓存
opcache.memory_consumption = 256       # MB。WordPress + 插件多的话给 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000  # ★ 文件数上限，见下方
opcache.revalidate_freq = 60           # 每 60 秒检查一次文件有没有改
opcache.validate_timestamps = 1        # ★ 生产环境也建议保持 1
opcache.save_comments = 1              # ★ 必须为 1，见下方
opcache.fast_shutdown = 1
opcache.huge_code_pages = 1
```

**`max_accelerated_files` 要够大**。WordPress 核心约 2500 个 PHP 文件，加上主题和插件轻松过 8000。超了之后新文件缓存不进去，性能悄悄退化。看实际有多少：

```bash
find /www/wwwroot/example.com -name '*.php' | wc -l
```

设成这个数的 1.5 倍。注意 PHP 会向上取到下一个质数。

**`opcache.save_comments` 必须是 1**。设成 0 会剥掉所有 docblock 注释——而 WordPress 生态里有些代码（尤其是用注解的框架、以及 WooCommerce 的一部分）依赖 docblock。设成 0 的收益是省几 MB 内存，代价是难以排查的诡异 bug。

**`validate_timestamps`**：设成 0 性能更好（完全不检查文件改动），但**改了代码必须手动重启 PHP-FPM 才生效**。团队协作时这个坑很致命——「我明明改了啊」。除非有严格的部署流程，否则保持 1。

监控命中率：

```bash
/www/server/php/82/bin/php -r '
$s = opcache_get_status(false);
printf("Hit rate: %.2f%%\nMemory used: %.1f MB / %.1f MB\nCached files: %d / %d\nOOM restarts: %d\n",
  $s["opcache_statistics"]["opcache_hit_rate"],
  $s["memory_usage"]["used_memory"]/1048576,
  ($s["memory_usage"]["used_memory"]+$s["memory_usage"]["free_memory"])/1048576,
  $s["opcache_statistics"]["num_cached_scripts"],
  $s["opcache_statistics"]["max_cached_keys"],
  $s["opcache_statistics"]["oom_restarts"]);
'
```

（注意这是 CLI，`enable_cli=0` 时读不到 FPM 的状态——实际要通过一个 web 页面读，比如装 `opcache-gui`。）

**命中率应该 > 99%，`oom_restarts` 应该是 0**。`oom_restarts` 不为 0 说明 `memory_consumption` 不够。

### 12.2 Redis 对象缓存

WordPress 有个内置的对象缓存（`WP_Object_Cache`），但**默认只在单个请求内有效**——请求结束就没了。接上 Redis 之后，缓存跨请求持久化，能省掉大量重复的数据库查询。

装 Redis：

```
面板 → 软件商店 → Redis（安装）
面板 → 软件商店 → PHP 8.2 → 安装扩展 → redis
```

配置 Redis（面板 → Redis → 设置，或 `/www/server/redis/redis.conf`）：

```ini
bind 127.0.0.1                    # ★ 只监听本地，绝不能开公网
protected-mode yes
requirepass 一个强密码             # ★ 即使只监听本地也要设
maxmemory 256mb                   # ★ 必须设上限，否则会吃光内存
maxmemory-policy allkeys-lru      # ★ 满了淘汰最久未用的键
save ""                           # 对象缓存不需要持久化，关掉能省 IO
appendonly no
```

> 🔒 **Redis 未授权访问是历史上被利用最多的漏洞之一**。默认配置下 Redis 无密码、监听 0.0.0.0，攻击者可以直接写 SSH 公钥或 crontab 拿到服务器。`bind 127.0.0.1` + `requirepass` 两条都要有。

WordPress 侧装 **Redis Object Cache** 插件，然后在 `wp-config.php` 里：

```php
define( 'WP_REDIS_HOST', '127.0.0.1' );
define( 'WP_REDIS_PORT', 6379 );
define( 'WP_REDIS_PASSWORD', '你的Redis密码' );
define( 'WP_REDIS_DATABASE', 0 );          // ★ 多站点时每个站用不同的库号
define( 'WP_CACHE_KEY_SALT', 'example.com:' );  // ★ 或者用不同的前缀
define( 'WP_REDIS_MAXTTL', 86400 );
```

**多个站点共用一个 Redis 时，`WP_REDIS_DATABASE` 或 `WP_CACHE_KEY_SALT` 必须区分开**，否则 A 站会读到 B 站的缓存——这个 bug 表现为「偶尔显示别的网站的内容」，极难排查。

后台 → 设置 → Redis → Enable Object Cache。

验证：

```bash
redis-cli -a '你的密码' info stats | grep keyspace
# keyspace_hits 和 keyspace_misses，命中率 = hits/(hits+misses)

redis-cli -a '你的密码' info memory | grep used_memory_human
redis-cli -a '你的密码' dbsize
```

### 12.3 页面缓存

**这是收益最大的一层**——命中缓存时 PHP 和 MySQL 完全不参与，一个请求从几百毫秒降到几毫秒。

**但有严格的适用限制**：

| 场景 | 能不能整页缓存 |
|---|---|
| 企业站、博客、资讯站 | ✅ 完美适用 |
| 有登录用户的会员站 | ⚠️ 必须对登录用户绕过缓存 |
| **WooCommerce** | ⚠️ **购物车/结算/账户页必须排除**，否则会串单 |
| 每个用户看到不同内容 | ❌ 别用整页缓存 |

**方案一：WordPress 插件**（推荐，最省心）

- **WP Super Cache** — 简单，生成静态 HTML 文件
- **W3 Total Cache** — 功能全但配置复杂，容易配错
- **LiteSpeed Cache** — 最好用，但要配 OpenLiteSpeed/LSWS 服务器才能发挥全部能力
- **WP Rocket** — 付费，开箱即用

**方案二：Nginx FastCGI Cache**（性能最好，PHP 完全不启动）

在 `nginx.conf` 的 `http` 块：

```nginx
fastcgi_cache_path /www/fastcgi_cache
    levels=1:2
    keys_zone=WPCACHE:100m
    inactive=60m
    max_size=1g
    use_temp_path=off;

fastcgi_cache_key "$scheme$request_method$host$request_uri";
fastcgi_cache_use_stale error timeout invalid_header updating http_500 http_503;
fastcgi_cache_lock on;                 # 同一个 key 只让一个请求去回源
add_header X-FastCGI-Cache $upstream_cache_status;   # HIT/MISS/BYPASS，调试用
```

站点配置里：

```nginx
# ---- 决定哪些请求跳过缓存 ----
set $skip_cache 0;

# POST 请求不缓存
if ($request_method = POST) { set $skip_cache 1; }

# 带查询串的不缓存（搜索、分页参数等）
if ($query_string != "") { set $skip_cache 1; }

# 后台、登录、REST、cron、sitemap
if ($request_uri ~* "/wp-admin/|/wp-login.php|/wp-json/|/wp-cron.php|sitemap(_index)?.xml|/xmlrpc.php") {
    set $skip_cache 1;
}

# ★ WooCommerce 的动态页面，一定要排除
if ($request_uri ~* "/cart|/checkout|/my-account|/wc-api|/addons|\?add-to-cart=") {
    set $skip_cache 1;
}

# ★ 登录用户、评论过的用户、有购物车的用户
if ($http_cookie ~* "comment_author|wordpress_[a-f0-9]+|wp-postpass|wordpress_logged_in|woocommerce_items_in_cart|woocommerce_cart_hash") {
    set $skip_cache 1;
}

location ~ [^/]\.php(/|$) {
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    if (!-f $document_root$fastcgi_script_name) { return 404; }

    fastcgi_pass unix:/tmp/php-cgi-82.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

    fastcgi_cache_bypass $skip_cache;   # 命中这些条件时不读缓存
    fastcgi_no_cache     $skip_cache;   # 也不写缓存
    fastcgi_cache WPCACHE;
    fastcgi_cache_valid 200 301 302 60m;
    fastcgi_cache_valid 404 1m;
}
```

验证：

```bash
curl -I https://example.com/ | grep X-FastCGI-Cache
# 第一次: MISS    第二次: HIT
curl -I https://example.com/wp-admin/ | grep X-FastCGI-Cache
# 应该是: BYPASS
```

**清缓存**（改了内容之后）：

```bash
rm -rf /www/fastcgi_cache/*
```

要做到「发布文章自动清缓存」，装 **Nginx Helper** 插件，它会在文章更新时删掉对应的缓存文件。需要 Nginx 编译了 `ngx_cache_purge` 模块，或者用它的「删除文件」模式。

> ⚠️ **FastCGI Cache 的最大风险是缓存污染**：某个条件没排除干净，导致一个登录用户的页面（带着他的用户名、购物车）被缓存下来发给所有人。上线前必须**用两个不同账号 + 一个未登录浏览器交叉验证**。
>
> WooCommerce 站点上，如果你不确定，**就别用 FastCGI Cache**，用插件方案（它们对 Woo 有专门适配）。

### 12.4 浏览器缓存

见第 7.2 节的静态资源 location。要点：

- 带内容哈希的文件（`app.a3f9c2.js`）可以 `immutable` + 一年
- 不带哈希的（`style.css?ver=1.0`）用 30 天，靠 query string 刷新
- **HTML 绝不能长缓存**，否则改了内容用户看不到

---
## 13. 文件权限与属主

权限配错是 WordPress 上最常见也最危险的问题之一：配太松等于把服务器送人，配太紧则后台什么都干不了。

### 原则

**PHP 以 `www` 用户运行**（宝塔的默认），所以：

- WordPress 需要写的目录 → 属主必须是 `www`
- WordPress **不需要**写的文件 → 属主也可以是 `www`，但**权限不能给写**
- **任何时候都不要用 777**

### 标准设置

```bash
SITE=/www/wwwroot/example.com

# 1. 属主统一
chown -R www:www $SITE

# 2. 目录 755（属主可读写进入，其他人只能读和进入）
find $SITE -type d -exec chmod 755 {} \;

# 3. 文件 644
find $SITE -type f -exec chmod 644 {} \;

# 4. wp-config.php 更严（里面有数据库密码）
chmod 640 $SITE/wp-config.php

# 5. 确认上传目录可写
chmod -R 755 $SITE/wp-content/uploads
```

宝塔面板有一键的：文件 → 选中站点目录 → 权限 → 设置 `755` + 属主 `www`，勾选「应用到子目录」。

### 各目录的权限要求

| 路径 | 权限 | 说明 |
|---|---|---|
| 站点根目录 | 755 | |
| `wp-config.php` | **640** | 含数据库密码。600 更严但某些场景 www 组要读 |
| `wp-content/` | 755 | |
| `wp-content/uploads/` | 755，属主 www | **必须可写**，否则传不了图 |
| `wp-content/plugins/` | 755，属主 www | 要在线装插件就得可写 |
| `wp-content/themes/` | 755，属主 www | 同上 |
| `wp-content/cache/` | 755，属主 www | 缓存插件要写 |
| `wp-content/upgrade/` | 755，属主 www | 自动更新的临时目录，**缺了会导致更新失败** |
| `.htaccess`（如果有） | 644 | Nginx 下没用，但保留无害 |

### 为什么不能用 777

`777` = 任何用户都能读写执行。后果：

1. **任何一个被攻破的站点都能改你的站**。同一台机器上跑了 10 个站，其中一个用了有漏洞的插件，攻击者拿到 `www` 权限后可以改所有 777 的文件。
2. 很多虚拟主机和安全扫描会直接**拒绝**执行 777 的 PHP 文件。
3. 出问题时**无法追溯**是谁改的。

「传不了图片就 chmod 777」是最常见的错误修法。正确的做法是查属主：

```bash
ls -la /www/wwwroot/example.com/wp-content/
# 如果 uploads 的属主是 root，PHP（www 用户）就写不进去
chown -R www:www /www/wwwroot/example.com/wp-content/uploads
```

**属主是 root 最常见的成因**：你用 root 跑了 `wp-cli`、`unzip`、`git pull` 或 `tar -x`。任何用 root 操作过站点目录之后，都要补一句 `chown -R www:www`。

### 让 WordPress 用直接文件系统

WordPress 装插件时如果检测到文件不可写，会弹出要求填 FTP 账号的界面。属主正确的话就不会弹。强制指定：

```php
// wp-config.php
define( 'FS_METHOD', 'direct' );
```

**但这只在权限真的正确时才管用**——它只是跳过检测，不是绕过操作系统权限。

### 加固：让部分目录不可写

安全性要求高的站点，可以让 PHP 无法修改代码（防止被上传 webshell 后改文件）：

```bash
SITE=/www/wwwroot/example.com

# 代码目录属主给 root，PHP 只能读
chown -R root:www $SITE
chmod -R 750 $SITE

# 只有这几个目录给 www 写
chown -R www:www $SITE/wp-content/uploads
chown -R www:www $SITE/wp-content/cache
chmod -R 755 $SITE/wp-content/uploads $SITE/wp-content/cache
```

代价是**后台无法在线更新插件/主题/核心**，必须走 SSH 或 WP-CLI 部署。对于交付给客户自己维护的站点不适用；对于你自己维护、走 Git 部署的站点是很好的加固。

### SELinux（AlmaLinux / Rocky）

RHEL 系默认开着 SELinux，即使权限对了也可能被拒。

```bash
getenforce                                   # Enforcing / Permissive / Disabled

# 排查是不是 SELinux 拦的
ausearch -m avc -ts recent

# 正确做法：给目录打对的标签
semanage fcontext -a -t httpd_sys_rw_content_t "/www/wwwroot/example.com/wp-content/uploads(/.*)?"
restorecon -Rv /www/wwwroot/example.com/wp-content/uploads

# 允许 PHP 对外发起网络连接（很多插件需要）
setsebool -P httpd_can_network_connect on
```

宝塔的安装脚本一般会直接关掉 SELinux。**关掉能省事，但也丢了一层纵深防御**——生产环境建议保持 Enforcing 并正确打标签。

---

## 14. 备份与恢复

**没有验证过能恢复的备份，等于没有备份。** 这一节的重点在最后那一小节。

### 3-2-1 原则

- **3** 份副本（生产 + 2 份备份）
- **2** 种介质
- **1** 份异地

宝塔的默认备份是存在**同一台服务器的 `/www/backup/`**——服务器炸了备份跟着炸，磁盘满了备份还会把站点一起拖死。**必须配置远程存储。**

### 面板定时备份

面板 → 计划任务 → 添加任务：

| 任务类型 | 备份网站 / 备份数据库 |
|---|---|
| 执行周期 | 每天 03:00（低峰期） |
| 备份到 | 阿里云 OSS / 腾讯云 COS / S3 / FTP（**不要选服务器磁盘**） |
| 保留份数 | 7–30 |

远程存储需要先在「软件商店 → 一键部署 / 第三方云存储」里装对应插件并填 AK/SK。

### 命令行备份

面板的备份不透明，自己写脚本更可控：

```bash
#!/bin/bash
# /root/scripts/backup.sh
set -euo pipefail

SITE_NAME="example.com"
SITE_DIR="/www/wwwroot/${SITE_NAME}"
DB_NAME="example_com"
DB_USER="example_com"
DB_PASS="数据库密码"
BACKUP_DIR="/www/backup/manual"
DATE=$(date +%Y%m%d_%H%M%S)
KEEP_DAYS=7

mkdir -p "$BACKUP_DIR"

# ---- 数据库 ----
# --single-transaction: InnoDB 下不锁表做一致性快照
# --quick: 逐行取，不把整个结果集读进内存
# --default-character-set: 防止中文变问号
mysqldump -u"$DB_USER" -p"$DB_PASS" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -9 > "${BACKUP_DIR}/${SITE_NAME}_db_${DATE}.sql.gz"

# ---- 文件 ----
# 排除缓存和日志，能省一大半体积
tar -czf "${BACKUP_DIR}/${SITE_NAME}_files_${DATE}.tar.gz" \
  --exclude='wp-content/cache' \
  --exclude='wp-content/uploads/wc-logs' \
  --exclude='wp-content/debug.log' \
  --exclude='*.log' \
  --exclude='.user.ini' \
  -C /www/wwwroot "$SITE_NAME"

# ---- 清理旧备份 ----
find "$BACKUP_DIR" -name "${SITE_NAME}_*" -mtime +${KEEP_DAYS} -delete

# ---- 同步到异地 ----
# rclone（推荐，支持几十种对象存储）
# rclone copy "$BACKUP_DIR" remote:bucket/backups/ --max-age 24h

# 或者 rsync 到另一台机器
# rsync -az --delete "$BACKUP_DIR/" backup@10.0.0.9:/backups/${SITE_NAME}/

echo "[$(date)] backup done: ${SITE_NAME}_${DATE}"
```

```bash
chmod 700 /root/scripts/backup.sh        # ★ 里面有数据库密码
crontab -e
# 0 3 * * * /root/scripts/backup.sh >> /var/log/backup.log 2>&1
```

**把密码写在脚本里不理想**，更好的做法是用 `~/.my.cnf`：

```bash
cat > /root/.my.cnf <<'EOF'
[client]
user=root
password=你的密码
EOF
chmod 600 /root/.my.cnf
```

然后 `mysqldump` 就不用带 `-u -p` 了。

### 恢复

```bash
# ---- 数据库 ----
# 先建空库（如果是全新环境）
mysql -u root -p -e "CREATE DATABASE example_com CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

zcat example.com_db_20260908_030000.sql.gz | mysql -u root -p example_com

# ---- 文件 ----
cd /www/wwwroot
tar -xzf example.com_files_20260908_030000.tar.gz
chown -R www:www example.com

# ---- 属主和权限一定要补 ----
find example.com -type d -exec chmod 755 {} \;
find example.com -type f -exec chmod 644 {} \;
chmod 640 example.com/wp-config.php
```

### ★ 定期做恢复演练

**这是这一节唯一真正重要的事。**

备份最常见的失效方式不是「没备份」，而是：

- mysqldump 因为权限问题只备份了部分表，但脚本没检查退出码（`set -e` 能挡一部分）
- gzip 文件损坏
- 备份的是空数据库（连错了库名）
- 磁盘满了，备份写了一半
- 备份脚本三个月前就报错了，但 cron 的输出没人看

**每季度做一次演练**：

```bash
# 在测试环境（或同一台机器的另一个库）恢复一次
mysql -u root -p -e "CREATE DATABASE restore_test;"
zcat 最新的备份.sql.gz | mysql -u root -p restore_test

# 检查关键表有数据
mysql -u root -p restore_test -e "
  SELECT 'posts' t, COUNT(*) n FROM wp_posts
  UNION SELECT 'users', COUNT(*) FROM wp_users
  UNION SELECT 'options', COUNT(*) FROM wp_options;"

mysql -u root -p -e "DROP DATABASE restore_test;"
```

再加一个「备份健康检查」的定时任务，备份文件太小或太旧就告警：

```bash
#!/bin/bash
# /root/scripts/check-backup.sh
LATEST=$(ls -t /www/backup/manual/*_db_*.sql.gz 2>/dev/null | head -1)

if [ -z "$LATEST" ]; then
    echo "ALERT: no backup found"; exit 1
fi

SIZE=$(stat -c%s "$LATEST")
AGE_H=$(( ($(date +%s) - $(stat -c%Y "$LATEST")) / 3600 ))

[ "$SIZE" -lt 102400 ] && echo "ALERT: backup too small (${SIZE} bytes): $LATEST"
[ "$AGE_H" -gt 26 ]    && echo "ALERT: backup too old (${AGE_H}h): $LATEST"

# gzip 完整性
gzip -t "$LATEST" || echo "ALERT: backup corrupted: $LATEST"
```

---

## 15. 站点迁移

从虚拟主机 / 老服务器迁到宝塔，或宝塔之间迁移。

### 15.1 迁移前的准备

```
□ 确认新服务器的 PHP 版本 ≥ 老服务器（低了可能跑不起来）
□ 确认 PHP 扩展齐全（对比 php -m 的输出）
□ 确认 MySQL 版本兼容（5.7 → 8.0 通常没问题，反向可能不行）
□ 记下老服务器的 wp-config.php 内容
□ 记下所有定时任务
□ 记下所有 DNS 记录（不只是 A 记录，还有 MX、TXT、CNAME）
□ 记下 SSL 证书的到期时间和签发方式
```

### 15.2 标准流程

```bash
# ========== 老服务器 ==========
cd /www/wwwroot

# 导数据库
mysqldump -u root -p --single-transaction --quick --routines --triggers \
  --default-character-set=utf8mb4 old_db | gzip > /tmp/db.sql.gz

# 打包文件（★ 排除 .user.ini，它含老服务器的路径）
tar -czf /tmp/site.tar.gz \
  --exclude='wp-content/cache' \
  --exclude='.user.ini' \
  --exclude='*.log' \
  example.com

# 传到新服务器
scp /tmp/db.sql.gz /tmp/site.tar.gz root@新IP:/tmp/
# 大文件建议用 rsync，断了能续
# rsync -avz --progress /tmp/site.tar.gz root@新IP:/tmp/


# ========== 新服务器 ==========
# 1. 先在面板里建好站点和数据库（第 10.2 节）

# 2. 解包
cd /www/wwwroot
rm -rf example.com/*                    # 清掉面板生成的默认页
tar -xzf /tmp/site.tar.gz

# 3. 导数据库
zcat /tmp/db.sql.gz | mysql -u root -p new_db

# 4. 改 wp-config.php 的数据库连接
vim /www/wwwroot/example.com/wp-config.php
#    DB_NAME / DB_USER / DB_PASSWORD 改成新服务器的

# 5. ★ 属主权限
chown -R www:www /www/wwwroot/example.com
find /www/wwwroot/example.com -type d -exec chmod 755 {} \;
find /www/wwwroot/example.com -type f -exec chmod 644 {} \;

# 6. 重新生成 .user.ini（在面板里改一下站点的 PHP 版本再改回来，会自动重建）
#    或者手写：
chattr -i /www/wwwroot/example.com/.user.ini 2>/dev/null
echo 'open_basedir=/www/wwwroot/example.com/:/tmp/' > /www/wwwroot/example.com/.user.ini
```

### 15.3 换域名：必须用 WP-CLI

**这是迁移里最容易出错的一步。**

WordPress 的很多数据（页面构建器的布局、ACF 的部分字段、小工具配置）是以 **PHP 序列化字符串**存在数据库里的：

```
a:2:{s:3:"url";s:23:"https://old-domain.com/";s:5:"title";s:4:"Home";}
        ↑                    ↑
     键名长度            值的长度 = 23
```

用 SQL 的 `REPLACE()` 把 `old-domain.com` 换成 `new-domain.com.cn`，字符串变长了但**前面记录的长度 23 没变**，反序列化直接失败 → 那个字段的数据全部丢失。表现是「迁移后页面构建器的内容全空了」。

**正确做法**：

```bash
cd /www/wwwroot/example.com

# 先干跑，看会改多少条
wp search-replace 'https://old-domain.com' 'https://new-domain.com' --dry-run --all-tables

# 确认无误后执行
wp search-replace 'https://old-domain.com' 'https://new-domain.com' --all-tables --precise

# 协议也换了的话，再跑一遍 http
wp search-replace 'http://old-domain.com' 'https://new-domain.com' --all-tables --precise

# 老服务器的绝对路径（上传路径等）
wp search-replace '/home/oldsite/public_html' '/www/wwwroot/example.com' --all-tables
```

`wp search-replace` 会**正确处理序列化数据**——它反序列化、替换、再序列化。

没有 WP-CLI 的话，用 **Better Search Replace** 插件（同样正确处理序列化），或者 interconnect/it 的 Search-Replace-DB 脚本。**绝对不要直接跑 SQL 的 REPLACE**。

### 15.4 收尾

```bash
# 刷新固定链接（Nginx 下伪静态在配置里，但 WP 的重写规则缓存要刷）
wp rewrite flush --hard

# 清各种缓存
wp cache flush
wp transient delete --all
rm -rf /www/wwwroot/example.com/wp-content/cache/*
redis-cli -a '密码' FLUSHDB          # 如果用了 Redis

# 检查
wp option get siteurl
wp option get home
wp core verify-checksums              # ★ 核对核心文件有没有被篡改
wp plugin list --status=active
```

### 15.5 切 DNS 的正确顺序

1. **提前 24–48 小时**把域名的 TTL 从默认（通常 3600 或更长）**降到 300 秒**
2. 在新服务器上用 `hosts` 文件绑定测试，确认站点完全正常

```
# 本地机器的 hosts 文件
新服务器IP    example.com www.example.com
```

3. 全面测试：首页、文章页、后台登录、表单提交、支付流程、图片显示
4. **老服务器设为只读**（或挂维护页），防止切换期间产生新数据丢失
5. 改 DNS 指向新 IP
6. 观察两边的访问日志，直到老服务器没有流量了
7. 老服务器保留至少 7 天再关

**电商站的额外注意**：切换窗口期内会有订单落在两边。要么选在凌晨零单时段，要么先把商城设成暂停下单。

### 15.6 迁移后检查清单

```
□ 首页、内页、分类页、搜索页都能打开（不是 404）
□ 后台能登录
□ 图片能正常显示（检查媒体库，看有没有裂图）
□ 能上传新图片
□ 固定链接正常
□ 表单能提交且能收到邮件
□ HTTPS 正常，无混合内容警告（F12 看 Console）
□ 定时任务已重建（wp cron event list）
□ 数据库里没有残留的旧域名：
   wp db query "SELECT COUNT(*) FROM wp_options WHERE option_value LIKE '%old-domain%'"
□ robots.txt 和 sitemap 正常
□ 支付回调地址已在支付平台后台更新（★ 极易漏）
□ 第三方服务的白名单 IP 已更新（★ 极易漏）
```

---
## 16. 定时任务

### WP-Cron 的问题

WordPress 自带的 WP-Cron **不是真正的定时任务**。它的机制是：**每次有人访问网站时，检查有没有到期的任务，有就在这次请求里执行**。

后果：

1. **没人访问就不执行**。低流量站点的定时发布、备份、订阅邮件全部延迟。
2. **高流量站点上，每个请求都要多做一次检查**（一次数据库查询 + 一个内部 HTTP 请求到 `wp-cron.php`），是性能负担。
3. **执行时机不可控**。有人在访问的那一刻恰好触发了一个耗时任务，那个访客就要等。
4. 被恶意刷 `wp-cron.php` 可以造成 DoS。

**正确做法**：关掉 WP-Cron，用系统 cron 定时调用。

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```bash
crontab -e
```

```cron
# 每 5 分钟跑一次 WordPress 的到期任务
*/5 * * * * cd /www/wwwroot/example.com && /usr/local/bin/wp cron event run --due-now --quiet >/dev/null 2>&1
```

或者不用 WP-CLI，直接请求（次一点，因为会走完整的 HTTP 栈）：

```cron
*/5 * * * * curl -s -o /dev/null "https://example.com/wp-cron.php?doing_wp_cron"
```

**WP-CLI 的方式更好**：不走 HTTP、不占 PHP-FPM 的 worker、有独立的超时和内存限制、报错能被 cron 捕获。

宝塔面板里也能加：计划任务 → Shell 脚本，把上面的命令贴进去。

### 检查定时任务

```bash
cd /www/wwwroot/example.com

wp cron event list                     # 列出所有任务和下次执行时间
wp cron event list --fields=hook,next_run_relative,recurrence

wp cron test                           # 测试 cron 能不能正常跑
wp cron event run some_hook_name       # 手动跑某个任务
wp cron event delete some_hook_name    # 删掉卡住的任务
```

**看到一堆 `next_run` 是过去时间的任务**，说明 cron 没在跑。

### crontab 的常见坑

**1. cron 的 PATH 极其精简**

cron 环境下 `PATH` 通常只有 `/usr/bin:/bin`，你 shell 里能跑的命令在 cron 里可能找不到。

```cron
# ❌ 不行
0 3 * * * wp cron event run --due-now

# ✅ 全路径
0 3 * * * /usr/local/bin/wp --path=/www/wwwroot/example.com cron event run --due-now

# 或者在 crontab 顶部声明
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
```

**2. 百分号会被 cron 当成换行符**

```cron
# ❌ date +%Y 里的 % 会被吃掉
0 3 * * * tar -czf /backup/site_$(date +%Y%m%d).tar.gz /www/wwwroot

# ✅ 转义
0 3 * * * tar -czf /backup/site_$(date +\%Y\%m\%d).tar.gz /www/wwwroot

# ✅ 更好：写进脚本文件，cron 只调脚本
0 3 * * * /root/scripts/backup.sh
```

**3. 没有重定向输出 = 出错了没人知道**

```cron
# ✅ 记日志
0 3 * * * /root/scripts/backup.sh >> /var/log/backup.log 2>&1

# 或者让 cron 把输出邮件给你
MAILTO=you@example.com
```

**4. 相对路径**

cron 的工作目录是用户家目录，不是脚本所在目录。脚本里一律用绝对路径，或者开头先 `cd`。

**5. 任务重叠**

上一次还没跑完，下一次又启动了。用 `flock` 加锁：

```cron
*/5 * * * * /usr/bin/flock -n /tmp/wpcron.lock /usr/local/bin/wp --path=/www/wwwroot/example.com cron event run --due-now
```

`-n` 的含义是「拿不到锁就直接退出」，不排队。

### 常用定时任务模板

```cron
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
MAILTO=""

# WordPress cron，每 5 分钟
*/5 * * * * /usr/bin/flock -n /tmp/wpcron.lock /usr/local/bin/wp --path=/www/wwwroot/example.com cron event run --due-now --quiet

# 数据库+文件备份，每天 3:00
0 3 * * * /root/scripts/backup.sh >> /var/log/backup.log 2>&1

# 备份健康检查，每天 4:00
0 4 * * * /root/scripts/check-backup.sh >> /var/log/backup.log 2>&1

# 清理过期 transient，每天 4:30
30 4 * * * /usr/local/bin/wp --path=/www/wwwroot/example.com transient delete --expired --quiet

# Let's Encrypt 续期检查，每天 2:30（宝塔会自建，别重复）
30 2 * * * /root/.acme.sh/acme.sh --cron --home /root/.acme.sh > /dev/null

# 磁盘空间告警，每小时
0 * * * * df -h / | awk 'NR==2 && int($5) > 85 {print "Disk usage: " $5}'

# 数据库优化，每周日 5:00
0 5 * * 0 /usr/local/bin/wp --path=/www/wwwroot/example.com db optimize --quiet
```

---

## 17. 日志：在哪、怎么读

排错的第一步永远是看日志。宝塔上日志分散在几个地方：

| 日志 | 路径 | 什么时候看 |
|---|---|---|
| 站点访问日志 | `/www/wwwlogs/example.com.log` | 分析流量、找攻击、查 404 |
| 站点错误日志 | `/www/wwwlogs/example.com.error.log` | **502/504/403 先看这个** |
| Nginx 全局错误 | `/www/wwwlogs/nginx_error.log` | Nginx 起不来、配置问题 |
| PHP 错误 | `/www/wwwlogs/php_error.log` | **500 / 白屏看这个** |
| PHP-FPM 主日志 | `/www/server/php/82/var/log/php-fpm.log` | max_children 告警、worker 崩溃 |
| PHP 慢日志 | `/www/wwwlogs/php-82-slow.log` | **页面慢、504 看这个**，有完整堆栈 |
| MySQL 错误 | `/www/server/mysql/*.err` | 数据库起不来、崩溃 |
| MySQL 慢查询 | `/www/server/mysql/mysql-slow.log` | 数据库慢 |
| WordPress 调试 | `站点/wp-content/debug.log` | 开了 WP_DEBUG_LOG 才有 |
| 面板日志 | `/www/server/panel/logs/` | 面板本身的问题 |
| 系统日志 | `/var/log/syslog` 或 `journalctl` | OOM、内核问题 |

### 常用查看方式

```bash
# 实时跟踪
tail -f /www/wwwlogs/example.com.error.log

# 同时跟多个
tail -f /www/wwwlogs/example.com.error.log /www/wwwlogs/php_error.log

# 带高亮过滤
tail -f /www/wwwlogs/example.com.log | grep --line-buffered -E ' (4|5)[0-9]{2} '

# 看最后 100 行
tail -100 /www/wwwlogs/php_error.log
```

### 访问日志分析（不用装任何工具）

```bash
LOG=/www/wwwlogs/example.com.log

# 访问量最大的 IP（找攻击源）
awk '{print $1}' $LOG | sort | uniq -c | sort -rn | head -20

# 访问最多的 URL
awk '{print $7}' $LOG | sort | uniq -c | sort -rn | head -20

# 状态码分布
awk '{print $9}' $LOG | sort | uniq -c | sort -rn

# 所有 5xx 请求
awk '$9 ~ /^5/ {print}' $LOG | tail -50

# 所有 404（找死链）
awk '$9 == 404 {print $7}' $LOG | sort | uniq -c | sort -rn | head -30

# 最慢的请求（需要日志格式里有 rt=）
grep -oE 'rt=[0-9.]+ [^ ]*' $LOG | sort -t= -k2 -rn | head -20

# 按小时统计流量
awk '{print substr($4, 2, 14)}' $LOG | uniq -c

# 爬虫流量
grep -icE 'bot|spider|crawler' $LOG

# 谁在打 wp-login.php
grep 'wp-login.php' $LOG | awk '{print $1}' | sort | uniq -c | sort -rn | head -20

# 谁在扫 xmlrpc
grep 'xmlrpc.php' $LOG | awk '{print $1}' | sort | uniq -c | sort -rn | head
```

### 日志切割

**日志不切割会撑爆磁盘**，然后所有服务一起挂掉。这是最常见的「服务器突然全挂」原因。

宝塔在面板 → 网站 → 设置 → 网站日志 里有切割配置。系统层面用 logrotate：

```bash
cat > /etc/logrotate.d/bt-wwwlogs <<'EOF'
/www/wwwlogs/*.log {
    daily
    rotate 15
    missingok
    notifempty
    compress
    delaycompress
    dateext
    sharedscripts
    postrotate
        [ -f /www/server/nginx/logs/nginx.pid ] && kill -USR1 `cat /www/server/nginx/logs/nginx.pid`
    endscript
}
EOF

# 测试（不实际执行）
logrotate -d /etc/logrotate.d/bt-wwwlogs

# 强制执行一次
logrotate -f /etc/logrotate.d/bt-wwwlogs
```

`kill -USR1` 是让 Nginx **重新打开日志文件**——不发这个信号的话，Nginx 会继续往已经被改名的旧文件句柄里写，新文件永远是空的，磁盘也不会释放。这是 logrotate 配置里最容易漏的一行。

**检查磁盘和日志体积**：

```bash
df -h                                          # 整体
du -sh /www/wwwlogs/                           # 日志总量
du -sh /www/wwwlogs/* | sort -rh | head -10    # 最大的几个
du -sh /www/* | sort -rh                       # /www 下谁最大

# 找出整个系统里最大的文件
find / -xdev -type f -size +500M -exec ls -lh {} \; 2>/dev/null | sort -k5 -rh | head
```

### 磁盘满了的紧急处理

```bash
# 1. 先找出谁占的
du -sh /* 2>/dev/null | sort -rh | head

# 2. 常见元凶
du -sh /www/wwwlogs/          # 日志没切割
du -sh /www/backup/           # 备份堆积
du -sh /www/server/mysql/     # binlog 堆积
ls -lh /www/server/mysql/mysql-bin.*

# 3. 清 binlog（★ 不要直接 rm，用 MySQL 的命令）
mysql -u root -p -e "PURGE BINARY LOGS BEFORE DATE_SUB(NOW(), INTERVAL 3 DAY);"

# 4. 清老日志
find /www/wwwlogs -name '*.log' -mtime +7 -delete
truncate -s 0 /www/wwwlogs/example.com.log     # 清空但保留文件句柄

# 5. 清老备份
find /www/backup -mtime +14 -delete
```

> ⚠️ **不要用 `rm` 删正在被写入的日志文件**。文件被删了但 Nginx 还持有句柄，磁盘空间不会释放，直到重启 Nginx。用 `truncate -s 0` 或 `> file` 清空。

---

## 18. 排错手册（按症状索引）

### 502 Bad Gateway

**含义**：Nginx 连不上 PHP-FPM，或者 PHP-FPM 拒绝了连接。

```bash
# 1. PHP-FPM 在跑吗
ps aux | grep php-fpm | head
/etc/init.d/php-fpm-82 status

# 2. socket 文件在吗、权限对吗
ls -la /tmp/php-cgi-82.sock
# 应该是 srw-rw-rw- 且属主 www:www

# 3. Nginx 配置里指向的 socket 和实际的一致吗（★ 切 PHP 版本后最常见）
grep -r fastcgi_pass /www/server/panel/vhost/nginx/

# 4. 看 PHP-FPM 日志
tail -50 /www/server/php/82/var/log/php-fpm.log
tail -50 /www/wwwlogs/example.com.error.log

# 5. worker 是不是满了
curl 'http://127.0.0.1/fpm-status'
```

| 日志里的内容 | 原因 | 解决 |
|---|---|---|
| `connect() to unix:/tmp/php-cgi-82.sock failed (2: No such file)` | PHP-FPM 没起 | 启动它；看它为什么起不来 |
| `connect() ... failed (13: Permission denied)` | socket 权限 | `listen.mode = 0666`，`listen.owner = www` |
| `connect() ... failed (11: Resource temporarily unavailable)` | 队列满了 | 调大 `listen.backlog` 和 `pm.max_children` |
| `server reached pm.max_children` | worker 打满 | 调大，或先查慢请求（第 8 节） |
| `upstream sent too big header` | 响应头超过缓冲区 | 调大 `fastcgi_buffer_size` 和 `fastcgi_buffers` |

PHP-FPM 起不来时，直接前台跑看报错：

```bash
/www/server/php/82/sbin/php-fpm -t                    # 测配置
/www/server/php/82/sbin/php-fpm -F                    # 前台跑，Ctrl+C 退出
```

### 504 Gateway Timeout

**含义**：PHP 还在跑，但 Nginx 等超时了。**这不是 Nginx 的问题，是 PHP 太慢。**

```bash
# 1. 先看慢日志，它会告诉你卡在哪个函数
tail -100 /www/wwwlogs/php-82-slow.log

# 2. 看数据库慢查询
tail -50 /www/server/mysql/mysql-slow.log
```

**先修慢的原因，不要先调超时。** 调大超时只是让用户等更久。

常见的慢因：

- 插件在同步调用外部 API（支付、物流、翻译），对方响应慢
- `wp_options` 的 autoload 数据膨胀（第 9 节）
- 没索引的 `meta_query`
- 图片处理（生成缩略图）在 PHP 里跑
- 循环里做数据库查询（N+1）

确实需要更长时间的场景（大量数据导入、生成报表），**把那个操作挪到 CLI 去跑**，不要在 Web 请求里做。实在要调：

```nginx
fastcgi_read_timeout 300;       # Nginx 侧
```
```ini
max_execution_time = 300         ; php.ini
request_terminate_timeout = 300  ; php-fpm www.conf，要 ≥ 上面两个
```

三个都要改，取最小值生效。

### 500 Internal Server Error / 白屏

```bash
# 1. PHP 错误日志（★ 第一个看的地方）
tail -50 /www/wwwlogs/php_error.log

# 2. 站点错误日志
tail -50 /www/wwwlogs/example.com.error.log

# 3. 开 WordPress 调试日志
# wp-config.php:
#   define('WP_DEBUG', true);
#   define('WP_DEBUG_LOG', true);
#   define('WP_DEBUG_DISPLAY', false);
tail -f /www/wwwroot/example.com/wp-content/debug.log
```

日志也是空的时候（说明 PHP 根本没启动起来）：

```bash
# 在站点根目录放个最小测试文件
echo '<?php phpinfo();' > /www/wwwroot/example.com/info.php
curl -I https://example.com/info.php
rm /www/wwwroot/example.com/info.php     # ★ 测完立刻删，phpinfo 会泄露大量信息
```

**WordPress 白屏的标准排查顺序**：

```bash
cd /www/wwwroot/example.com

# 1. 停掉所有插件（最常见的原因）
wp plugin deactivate --all
# 好了 → 一个个开回来定位是哪个
wp plugin activate plugin-name

# 2. 换默认主题
wp theme activate twentytwentyfour

# 3. 核对核心文件有没有损坏或被篡改
wp core verify-checksums

# 4. 内存不够？
# wp-config.php: define('WP_MEMORY_LIMIT', '512M');
```

没有 WP-CLI 时，把 `wp-content/plugins` 改名成 `plugins_off`，WordPress 会自动停用所有插件。

### 数据库连接错误

```
Error establishing a database connection
```

```bash
# 1. MySQL 在跑吗
/etc/init.d/mysqld status
systemctl status mysqld

# 2. 能连上吗
mysql -u root -p -e "SELECT 1;"

# 3. 站点的账号密码对吗
grep -E "DB_(NAME|USER|PASSWORD|HOST)" /www/wwwroot/example.com/wp-config.php
mysql -u 站点用户 -p 站点库名 -e "SELECT COUNT(*) FROM wp_options;"

# 4. 连接数满了？
mysql -u root -p -e "SHOW STATUS LIKE 'Threads_connected'; SHOW VARIABLES LIKE 'max_connections';"

# 5. MySQL 错误日志
tail -100 /www/server/mysql/*.err
```

**MySQL 起不来的常见原因**：

| 日志里的 | 原因 |
|---|---|
| `Out of memory` / 被 OOM killer 杀了 | 内存不够，调小 `innodb_buffer_pool_size`，加 swap |
| `Can't create/write to file` | 磁盘满了，或 `/www/server/mysql/data` 权限不对 |
| `InnoDB: Unable to lock ./ibdata1` | 已经有一个 mysqld 在跑，或上次没干净退出的锁文件 |
| `Table 'x' is marked as crashed` | 表损坏，用 `mysqlcheck --auto-repair` |

确认是不是 OOM 杀的：

```bash
dmesg -T | grep -i -E 'killed process|out of memory'
grep -i 'out of memory' /var/log/syslog
```

### 图片上传失败

按这个顺序查：

```bash
# 1. 属主（最常见）
ls -la /www/wwwroot/example.com/wp-content/ | grep uploads
chown -R www:www /www/wwwroot/example.com/wp-content/uploads

# 2. 磁盘满
df -h

# 3. 四个体积限制（第 7.6 节）
grep -E 'upload_max_filesize|post_max_size|max_execution_time' /www/server/php/82/etc/php.ini
grep client_max_body_size /www/server/panel/vhost/nginx/example.com.conf

# 4. PHP 缺 GD 或 imagick 扩展
/www/server/php/82/bin/php -m | grep -iE 'gd|imagick'

# 5. open_basedir 限制（.user.ini）
cat /www/wwwroot/example.com/.user.ini
```

### 网站慢

按这个顺序定位：

```bash
# 1. 是服务器慢还是网络慢？本机直接请求
time curl -o /dev/null -s -w 'connect=%{time_connect} ttfb=%{time_starttransfer} total=%{time_total}\n' \
  http://127.0.0.1/ -H 'Host: example.com'
# TTFB 高 → 服务器端慢，往下查
# TTFB 低但 total 高 → 网络/带宽问题

# 2. 系统负载
uptime                        # load average 长期 > CPU 核数就是过载
top -bn1 | head -20
vmstat 1 5                    # 看 wa 列（IO 等待），高说明磁盘是瓶颈

# 3. 内存
free -h                       # available 少 + swap 在用 = 内存不够

# 4. 磁盘 IO
iostat -x 1 3                 # %util 接近 100 = 磁盘打满
iotop -o                      # 谁在读写

# 5. PHP 慢日志（★ 最直接）
tail -100 /www/wwwlogs/php-82-slow.log

# 6. 数据库慢查询
mysqldumpslow -s t -t 10 /www/server/mysql/mysql-slow.log

# 7. 当前在跑什么 SQL
mysql -u root -p -e "SHOW FULL PROCESSLIST;"
```

**从日志里找最慢的 URL**（需要日志格式带 `rt=`）：

```bash
awk '{for(i=1;i<=NF;i++) if($i ~ /^rt=/) {split($i,a,"="); print a[2], $7}}' \
  /www/wwwlogs/example.com.log | sort -rn | head -20
```

### 静态文件 404 但 PHP 正常

```bash
# 1. root 路径对吗
grep -A2 'server_name example.com' /www/server/panel/vhost/nginx/example.com.conf

# 2. 文件真的在吗
ls -la /www/wwwroot/example.com/wp-content/themes/theme/style.css

# 3. 上级目录有执行权限吗（755 里的那个 x）
namei -l /www/wwwroot/example.com/wp-content/themes/theme/style.css

# 4. 看 Nginx 认为它在哪
tail -f /www/wwwlogs/example.com.error.log
# 会打出 "open() /path/to/file failed (2: No such file or directory)"
```

第 4 条最有用——Nginx 的错误日志会直接打印它尝试打开的完整路径，一眼就能看出 root 拼错在哪。

### 无限重定向

```
ERR_TOO_MANY_REDIRECTS
```

几乎总是「WordPress 认为自己是 HTTPS，但收到的请求头说是 HTTP」造成的循环。

```bash
# 看重定向链
curl -sIL https://example.com/ | grep -E 'HTTP/|Location'

# 检查 WP 的设置
wp option get siteurl
wp option get home
# 两个都应该是 https://example.com（一致，且和实际访问的一致）
```

在 CDN / 反代后面时，加第 7.5 节那段 `HTTP_X_FORWARDED_PROTO` 的代码。

另一个成因是 **www 和非 www 互跳**：Nginx 把 `www` 跳到非 `www`，而 WordPress 的 `siteurl` 是 `www` 的，于是又跳回来。统一成一个。

---
## 19. 安全加固

### 19.1 SSH

```bash
vim /etc/ssh/sshd_config
```

```
Port 22222                        # 改掉默认端口，能挡掉 99% 的自动化扫描
PermitRootLogin prohibit-password # 只允许 root 用密钥登录（或 no，用普通用户+sudo）
PasswordAuthentication no         # ★ 只用密钥，彻底杜绝暴力破解
PubkeyAuthentication yes
MaxAuthTries 3
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
AllowUsers yourname               # 白名单，只有这些用户能 SSH
```

> ⚠️ **改之前一定要先把公钥传上去并用新配置测试成功，再关闭密码登录。** 保留当前 SSH 会话不要断，另开一个窗口测试新配置——测试失败还能用旧会话改回来。云服务器一般还有 VNC/控制台可以救，但不是所有厂商都有。

```bash
# 本地机器上传公钥
ssh-copy-id -p 22222 yourname@server-ip

# 服务器上应用
sshd -t                          # ★ 先测配置语法
systemctl reload sshd

# 另开窗口测试
ssh -p 22222 yourname@server-ip
```

改端口后记得同步云安全组，并且**宝塔面板的 SSH 管理里也要改**。

### 19.2 fail2ban

自动封禁反复失败的 IP。

```bash
apt install -y fail2ban          # 或 dnf install fail2ban

cat > /etc/fail2ban/jail.local <<'EOF'
[DEFAULT]
bantime  = 3600
findtime = 600
maxretry = 5
backend  = systemd
ignoreip = 127.0.0.1/8 你的固定IP

[sshd]
enabled = true
port    = 22222
maxretry = 3
bantime  = 86400

[nginx-http-auth]
enabled = true
logpath = /www/wwwlogs/*.error.log

[nginx-botsearch]
enabled = true
logpath = /www/wwwlogs/*.log
maxretry = 2

[wordpress-login]
enabled  = true
port     = http,https
filter   = wordpress-login
logpath  = /www/wwwlogs/*.log
maxretry = 5
findtime = 300
bantime  = 3600
EOF

# WordPress 登录的过滤规则
cat > /etc/fail2ban/filter.d/wordpress-login.conf <<'EOF'
[Definition]
failregex = ^<HOST> .* "POST /wp-login\.php.*" 200
            ^<HOST> .* "POST /xmlrpc\.php.*" 200
ignoreregex =
EOF

systemctl enable --now fail2ban
```

```bash
fail2ban-client status                      # 所有 jail
fail2ban-client status wordpress-login      # 某个 jail 的详情和封禁列表
fail2ban-client set wordpress-login unbanip 1.2.3.4    # 解封
```

> ⚠️ `wordpress-login` 这条规则匹配的是「POST 到 wp-login.php 且返回 200」——**登录成功也返回 200**（然后 302 跳转）。实际情况下 WordPress 登录成功是 302，失败是 200，所以这条规则基本正确，但**先用 `bantime = 300` 跑几天观察**，确认没有误封自己。

宝塔的「系统加固」和 Nginx 防火墙插件也提供类似能力，但 fail2ban 更透明可控。

### 19.3 防火墙

```bash
# ufw (Debian/Ubuntu)
ufw default deny incoming
ufw default allow outgoing
ufw allow 22222/tcp comment 'SSH'
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow from 你的IP to any port 面板端口 comment 'BT Panel'
ufw enable
ufw status numbered

# firewalld (RHEL 系)
firewall-cmd --permanent --add-port=22222/tcp
firewall-cmd --permanent --add-service=http --add-service=https
firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address=你的IP port port=面板端口 protocol=tcp accept'
firewall-cmd --reload
```

**MySQL 的 3306 和 Redis 的 6379 绝不能对公网开放。** 检查：

```bash
ss -tlnp | grep -E ':3306|:6379'
# 应该显示 127.0.0.1:3306，不是 0.0.0.0:3306
```

如果是 `0.0.0.0`，改配置：

```ini
# /etc/my.cnf
bind-address = 127.0.0.1

# /www/server/redis/redis.conf
bind 127.0.0.1
```

需要远程连数据库时，**用 SSH 隧道，不要开公网端口**：

```bash
ssh -N -L 3306:127.0.0.1:3306 -p 22222 yourname@server-ip
# 然后本地的 Navicat 连 127.0.0.1:3306
```

### 19.4 WordPress 专项

```
□ 管理员用户名不是 admin / administrator / 域名
□ 所有用户用强密码（后台 → 用户，逐个检查）
□ 装 Two Factor 或 WP 2FA 插件，给管理员开二次验证
□ 限制登录尝试（Limit Login Attempts Reloaded）
□ 改登录地址（WPS Hide Login）—— 挡自动化扫描很有效
□ 禁用 XML-RPC（第 7.2 节的 Nginx 规则）
□ 禁用 REST API 的用户枚举
□ 关掉后台文件编辑器（DISALLOW_FILE_EDIT）
□ 上传目录不执行 PHP（第 7.2 节）
□ 定期 wp core verify-checksums
□ 及时更新核心、插件、主题
□ 删掉停用的插件和主题（★ 停用不等于安全，代码文件还在，仍可被直接访问）
```

**禁用用户枚举**（攻击者用 `/?author=1` 或 `/wp-json/wp/v2/users` 拿用户名，然后暴力破解密码）：

```php
// mu-plugin 或主题 functions.php

// 1. 挡 /?author=N
add_action( 'template_redirect', function () {
    if ( is_author() && ! is_user_logged_in() ) {
        wp_safe_redirect( home_url(), 301 );
        exit;
    }
} );

// 2. 挡 REST API 的 users 端点
add_filter( 'rest_endpoints', function ( $endpoints ) {
    if ( ! is_user_logged_in() ) {
        unset( $endpoints['/wp/v2/users'] );
        unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
} );

// 3. 登录失败时不区分「用户名不存在」和「密码错误」
add_filter( 'login_errors', function () {
    return '用户名或密码不正确。';
} );

// 4. 移除 wp_head 里暴露版本号的 generator 标签
remove_action( 'wp_head', 'wp_generator' );
```

Nginx 层也挡一道：

```nginx
# 挡 ?author=N 枚举
if ($args ~* "author=([0-9]+)") {
    return 403;
}
```

### 19.5 被入侵后怎么办

**判断迹象**：

```bash
cd /www/wwwroot/example.com

# 1. 核心文件被改了吗
wp core verify-checksums

# 2. 最近 7 天被修改的 PHP 文件（★ 最有效的一招）
find . -name '*.php' -mtime -7 -ls

# 3. 上传目录里有 PHP 文件吗（正常情况一个都不该有）
find wp-content/uploads -name '*.php*'

# 4. 常见的 webshell 特征
grep -rn --include='*.php' -E 'eval\s*\(base64_decode|gzinflate\s*\(base64_decode|str_rot13\s*\(|\$_POST\[.{1,10}\]\s*\)\s*;|assert\s*\(\$_' .

# 5. 有没有多出来的管理员账号
wp user list --role=administrator

# 6. 有没有可疑的定时任务
wp cron event list
crontab -l
cat /etc/crontab; ls -la /etc/cron.d/

# 7. 系统层面
last -20                                  # 登录记录
grep 'Accepted' /var/log/auth.log | tail -20
ss -tnp                                   # 有没有奇怪的外连
ls -la /tmp /dev/shm                      # 常见的落马目录
```

**处置顺序**：

1. **立刻断开公网**（云安全组里把 80/443 关掉，或挂维护页）——**不要先删文件**，会破坏证据也可能漏掉后门
2. 完整打包当前状态留证（文件 + 数据库 + 日志）
3. 从**入侵之前**的干净备份恢复
4. 找到入侵入口（看访问日志里第一个 webshell 被访问的时间，往前翻）
5. 补上漏洞（通常是某个过时插件）
6. **换掉所有密码**：WP 管理员、数据库、SSH、面板、FTP、API key
7. 重新生成 `wp-config.php` 里的盐值（会踢掉所有登录会话）
8. 上线后持续观察日志一周

> **只删掉发现的 webshell 是不够的**。攻击者通常会留多个后门（改核心文件、加管理员、加定时任务、改 `wp_options` 里的 `active_plugins`）。除非你能完整确认所有改动，否则**从干净备份恢复是唯一可靠的做法**。这也是第 14 节强调「验证过的备份」的原因。

---

## 20. 性能压测与容量规划

### 压测工具

```bash
# ab（Apache Bench，最简单）
apt install -y apache2-utils
ab -n 1000 -c 20 https://example.com/

# wrk（更现代，能压出更高的并发）
apt install -y wrk
wrk -t4 -c100 -d30s --latency https://example.com/

# siege（能跑 URL 列表，更接近真实流量）
apt install -y siege
siege -c 50 -t 60s -f urls.txt
```

> ⚠️ **不要在生产站上压测**，尤其不要压 `/wp-admin/` 或会写数据库的 URL。用一个克隆的测试站。
>
> 也**不要从同一台服务器压自己**——压测工具会和被测服务抢 CPU，结果没有意义。从另一台机器压。

### 怎么读结果

```
Requests per second:    45.23 [#/sec] (mean)      ← 吞吐
Time per request:       442.4 [ms] (mean)         ← 平均响应
  50%    380ms                                    ← 中位数
  95%    890ms                                    ← ★ 看这个
  99%   2100ms                                    ← ★ 和这个
Failed requests:        0                         ← ★ 必须是 0
```

**看 P95 / P99，不要只看平均值。** 平均 400ms 但 P99 是 5 秒，意味着 1% 的用户体验极差——而这 1% 往往是最活跃的用户（页面多、请求多）。

`Failed requests` 不为 0 时，先看是 502（worker 打满）还是超时。

### 参考基准

单个 WordPress 页面（2 核 4G，开了 OPcache + Redis）：

| 配置 | 大致 QPS |
|---|---|
| 什么缓存都没有 | 5–15 |
| 只开 OPcache | 20–40 |
| OPcache + Redis 对象缓存 | 30–60 |
| **加整页缓存（FastCGI Cache）** | **500–3000** |
| WooCommerce 商品页（无整页缓存） | 5–20 |

**整页缓存是数量级的差异**，这就是为什么第 12 节把它排在最前面。

### 容量规划

从业务指标反推：

```
日 PV → 峰值 QPS：
  峰值 QPS ≈ 日 PV × 峰值系数 / 86400
  峰值系数取 3~5（流量不是均匀分布的，通常集中在几个小时）

例：日 PV 10 万
  10万 × 4 / 86400 ≈ 4.6 QPS 平均峰值

但一个"页面浏览"包含 HTML + 若干静态资源。
静态资源由 Nginx 直接返回，不占 PHP。
所以 PHP 侧的 QPS 约等于页面 QPS。

需要的 pm.max_children ≈ 峰值QPS × 平均响应时间(秒) × 安全系数2
  = 4.6 × 0.4 × 2 ≈ 4

看起来很少——因为大部分请求命中了缓存。
没有缓存的话，同样流量需要的 worker 数会翻好几倍。
```

**监控哪些指标**：

| 指标 | 警戒线 | 命令 |
|---|---|---|
| Load average | > CPU 核数 | `uptime` |
| 内存 available | < 15% | `free -h` |
| Swap 使用 | > 0 且持续增长 | `free -h` |
| 磁盘使用 | > 85% | `df -h` |
| 磁盘 %util | > 80% | `iostat -x 1` |
| PHP-FPM max_children reached | > 0 | `/fpm-status` |
| MySQL Threads_connected | > max_connections 的 70% | `SHOW STATUS` |
| OPcache 命中率 | < 99% | `opcache_get_status()` |
| Redis 内存 | 接近 maxmemory | `redis-cli info memory` |

宝塔的「监控」插件能画这些图，但**建议同时配置外部监控**（UptimeRobot、Better Uptime 之类）——服务器整个挂掉时，装在上面的监控也一起挂了，不会告警。

---

## 21. 多站点部署与资源隔离

一台机器跑多个站点时的注意事项。

### 21.1 PHP 进程池隔离

**默认所有站点共用一个 PHP-FPM 进程池**——意味着 A 站被刷爆会把 worker 全占了，B 站跟着 502。

给重要站点单独开一个池：

```ini
# /www/server/php/82/etc/php-fpm.d/important-site.conf
[important]
user = www
group = www
listen = /tmp/php-cgi-82-important.sock
listen.owner = www
listen.group = www
listen.mode = 0666

pm = dynamic
pm.max_children = 15
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000

php_admin_value[memory_limit] = 512M
php_admin_value[open_basedir] = /www/wwwroot/important.com/:/tmp/
php_admin_value[error_log] = /www/wwwlogs/important-php.log
slowlog = /www/wwwlogs/important-slow.log
request_slowlog_timeout = 5
```

```bash
/etc/init.d/php-fpm-82 restart
```

然后在站点的 Nginx 配置里指向新 socket：

```nginx
fastcgi_pass unix:/tmp/php-cgi-82-important.sock;
```

**注意**：所有池的 `max_children` 之和才是总的内存占用，别把每个池都按整机内存算。

`php_admin_value` 里设的值**站点代码无法用 `ini_set()` 覆盖**（`php_value` 可以），所以安全相关的用 `php_admin_value`。

### 21.2 用不同的系统用户

更强的隔离是**每个站点一个 Linux 用户**：

```bash
useradd -r -s /sbin/nologin -d /www/wwwroot/site-a site_a
chown -R site_a:site_a /www/wwwroot/site-a
```

```ini
[site_a]
user = site_a
group = site_a
listen = /tmp/php-cgi-82-site-a.sock
listen.owner = www          # ★ Nginx 是 www 用户，要能读这个 socket
listen.group = www
```

这样 A 站的 PHP 进程在文件系统层面就读不到 B 站的文件——即使 `open_basedir` 被绕过也一样。代价是运维复杂度上升（每个站的属主不同，部署脚本要相应处理）。

宝塔的默认方案（统一 `www` 用户 + `.user.ini` 的 `open_basedir`）对大多数场景够用；**给不同客户跑站时，建议上独立用户**。

### 21.3 数据库隔离

**每个站点一个数据库 + 一个专用账号**，账号只授权自己那个库：

```sql
CREATE DATABASE site_a CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'site_a'@'localhost' IDENTIFIED BY '强密码';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER,
      CREATE TEMPORARY TABLES, LOCK TABLES
  ON site_a.* TO 'site_a'@'localhost';
FLUSH PRIVILEGES;
```

**不要给 `ALL PRIVILEGES ON *.*`**，也不要多个站共用 root 账号——一个站的 SQL 注入就能读走所有站的数据。

宝塔建站时自动创建的账号已经是这个模式，但**手动迁移过来的站点经常直接用了 root**，检查一下：

```bash
grep DB_USER /www/wwwroot/*/wp-config.php
```

### 21.4 表前缀

同一个数据库里跑多个 WordPress 时，用不同的 `$table_prefix`。但**更推荐一站一库**——备份、恢复、迁移都独立，出问题不会互相牵连。

即使一站一库，也建议把 `$table_prefix` 从默认的 `wp_` 改掉（`wp_a3f9_`），能挡掉一部分自动化的 SQL 注入工具。

### 21.5 日志和备份分开

```
/www/wwwlogs/site-a.log
/www/wwwlogs/site-a.error.log
/www/backup/site-a/
```

宝塔默认就是这样。自己写备份脚本时保持这个结构，恢复单个站点才方便。

---

## 22. 命令行运维速查

### 宝塔面板

```bash
bt                     # 打开交互菜单（记不住的时候敲这个）
bt default             # ★ 查看面板地址 / 用户名 / 安全入口
bt 1                   # 重启面板
bt 2                   # 停止面板
bt 5                   # 修改密码
bt 6                   # 修改用户名
bt 8                   # 修改面板端口
bt 14                  # 查看/设置安全入口
bt 22                  # 显示面板错误日志
bt update              # 更新面板

# 解除各种自锁
rm -f /www/server/panel/data/limitip.conf     # IP 限制
rm -f /www/server/panel/data/domain.conf      # 域名绑定
rm -f /www/server/panel/data/admin_path.pl    # 安全入口
bt restart
```

### 服务管理

```bash
# 状态
/etc/init.d/nginx status
/etc/init.d/php-fpm-82 status
/etc/init.d/mysqld status
/etc/init.d/redis status

# Nginx（改配置的标准流程）
nginx -t                          # ★ 先测
nginx -s reload                   # 平滑重载
nginx -T | less                   # ★ 打印展开后的完整配置
nginx -V                          # 编译参数和模块列表

# PHP
/www/server/php/82/bin/php -v
/www/server/php/82/bin/php -m                 # 已装扩展
/www/server/php/82/bin/php -i | grep xxx      # 查某个配置的实际值
/www/server/php/82/sbin/php-fpm -t            # 测 FPM 配置
/etc/init.d/php-fpm-82 reload

# MySQL
mysql -u root -p
mysqladmin -u root -p status
mysqladmin -u root -p processlist
mysqladmin -u root -p extended-status | grep -i thread
```

### 系统状态

```bash
uptime                            # 负载
free -h                           # 内存
df -h                             # 磁盘
df -i                             # ★ inode（小文件多时会先耗尽 inode）
du -sh /www/* | sort -rh          # 谁占空间

top / htop                        # 进程
ps aux --sort=-%mem | head -15    # 内存 TOP
ps aux --sort=-%cpu | head -15    # CPU TOP

ss -tlnp                          # 监听端口
ss -tnp state established         # 已建立的连接
ss -s                             # 连接数汇总

iostat -x 1 3                     # 磁盘 IO
iotop -o                          # 谁在读写
vmstat 1 5                        # 综合（看 wa 列）

dmesg -T | tail -50               # 内核消息（OOM 在这）
journalctl -u nginx -n 50         # systemd 日志
journalctl -p err -n 50           # 所有错误级别的
```

### 进程和端口

```bash
lsof -i :80                       # 谁占了 80 端口
lsof -i :3306
fuser -k 80/tcp                   # 杀掉占用 80 的进程（慎用）

ps aux | grep php-fpm | wc -l     # PHP-FPM 进程数
pgrep -c nginx                    # Nginx 进程数

# 统计每个进程的实际内存（RSS 会重复计算共享内存，这个更准）
ps -eo rss,comm --sort=-rss | head -20
```

### WP-CLI 高频命令

```bash
cd /www/wwwroot/example.com

# 信息
wp core version
wp core check-update
wp core verify-checksums          # ★ 核对核心文件完整性
wp plugin list
wp theme list
wp user list

# 更新
wp core update
wp plugin update --all
wp theme update --all
wp language core update

# 数据库
wp db size --tables --human-readable
wp db export backup.sql
wp db import backup.sql
wp db optimize
wp db query "SELECT COUNT(*) FROM wp_posts"

# 搜索替换（★ 换域名必用）
wp search-replace 'old.com' 'new.com' --all-tables --dry-run
wp search-replace 'old.com' 'new.com' --all-tables --precise

# 缓存
wp cache flush
wp transient delete --all
wp transient delete --expired
wp rewrite flush --hard

# 用户
wp user create newadmin a@b.com --role=administrator --user_pass='强密码'
wp user update 1 --user_pass='新密码'      # ★ 忘密码时的救命命令
wp user list --role=administrator

# 插件（排查白屏）
wp plugin deactivate --all
wp plugin activate akismet

# cron
wp cron event list
wp cron event run --due-now
wp cron test

# 站点健康
wp doctor check --all             # 需装 wp-cli-doctor 包
```

### 一键诊断脚本

```bash
cat > /root/scripts/healthcheck.sh <<'HCEOF'
#!/bin/bash
echo "===== $(date) ====="
echo "--- Load / Uptime ---"; uptime
echo "--- Memory ---";        free -h
echo "--- Disk ---";          df -h / /www 2>/dev/null
echo "--- Inodes ---";        df -i / | tail -1
echo "--- Services ---"
for s in nginx mysqld redis; do
  printf "%-10s " "$s"
  /etc/init.d/$s status 2>/dev/null | head -1 || echo "unknown"
done
for v in /www/server/php/*/; do
  ver=$(basename "$v")
  printf "php-fpm-%-3s " "$ver"
  pgrep -f "php-fpm.*$ver" >/dev/null && echo "running" || echo "STOPPED"
done
echo "--- PHP-FPM procs ---"; pgrep -c php-fpm
echo "--- MySQL conns ---"
mysql -e "SHOW STATUS LIKE 'Threads_connected'" 2>/dev/null | tail -1
echo "--- Recent PHP errors ---"; tail -5 /www/wwwlogs/php_error.log 2>/dev/null
echo "--- Recent Nginx errors ---"; tail -5 /www/wwwlogs/nginx_error.log 2>/dev/null
echo "--- Largest logs ---"; du -sh /www/wwwlogs/* 2>/dev/null | sort -rh | head -5
HCEOF
chmod +x /root/scripts/healthcheck.sh
```

---

## 23. 宝塔的坑

用宝塔之前应该知道的事。

### 23.1 面板会覆写你手改的配置

在面板里改站点的任何设置（PHP 版本、域名、SSL、伪静态），宝塔都会**重新生成整个 vhost 文件**。你用 vim 手写进去的配置会消失。

**对策**：

1. 自定义配置写进单独文件，`include` 进来
2. 或者写在「伪静态」框里（那部分内容宝塔不动）
3. 或者放在标记块之外并做好备份

```bash
# 改之前先备份
cp -a /www/server/panel/vhost/nginx /www/server/panel/vhost/nginx.bak.$(date +%F)
```

### 23.2 .user.ini 的 immutable 属性

见第 5 节。`rm` 删不掉、`vim` 存不了，要先 `chattr -i`。

迁移站点时**必须排除这个文件**，否则新站会因为老服务器的 `open_basedir` 路径直接 500。

### 23.3 一键部署会装一堆东西

面板的「一键部署 WordPress」会装上宝塔自己打包的版本，可能带着你不需要的插件，而且版本未必是最新的。

**推荐用 WP-CLI 装**（第 10.3 节），干净可控。

### 23.4 自动更新可能引入问题

宝塔面板自身的自动更新偶尔会引入 bug 或改变行为。**生产环境建议关掉面板自动更新**，手动在测试环境验证后再更新。

面板 → 设置 → 自动更新，关掉。

### 23.5 免费版的功能限制

- 部分插件（Nginx 防火墙、WAF、监控报表的高级功能）要买专业版
- 免费版的备份到云存储需要装第三方插件
- 日志分析功能有限

这些用开源方案都能替代（fail2ban、logrotate、awk 分析日志），本文档基本都给了对应的手工做法。

### 23.6 国内版 vs 国际版

- **国内版（bt.cn）**：新版本要求绑定手机号 / 实名，部分功能需要登录宝塔账号。有过在未经明确同意的情况下收集信息的争议。
- **国际版（aaPanel）**：无强制绑定，英文界面。

**给海外客户做站、或介意数据合规的，用 aaPanel。**

### 23.7 安全历史

宝塔出过多次严重漏洞，最著名的是 2020 年 7.4.2 版本的 phpMyAdmin 未授权访问（访问 `IP:888/pma` 直接进数据库，无需密码）。

**结论不是「不要用宝塔」，而是**：

- 保持面板更新到最新的安全版本
- 严格执行第 4 节的加固
- 面板端口不对公网开放（用 SSH 隧道）
- 卸载不用的组件

### 23.8 卸载宝塔

```bash
# 官方卸载脚本
wget http://download.bt.cn/install/bt-uninstall.sh
bash bt-uninstall.sh
```

> ⚠️ **会删掉 `/www/` 下的所有东西，包括你的网站和数据库。** 卸载前完整备份并验证。

只想卸载面板但保留 LNMP 是做不到的——宝塔的 Nginx/PHP/MySQL 都装在 `/www/server/` 下并依赖面板的配置管理。要脱离宝塔，正确做法是**迁移到一台干净的机器上手工部署**（下一节）。

---

## 24. 不用宝塔：手工 LNMP 对照

理解手工部署能让你不依赖面板，也能在面板出问题时自救。这里给一份 Ubuntu 22.04 的最小可用流程。

```bash
# ---- 1. Nginx ----
apt update
apt install -y nginx
systemctl enable --now nginx

# ---- 2. PHP 8.2 + 扩展 ----
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.2-fpm php8.2-mysql php8.2-curl php8.2-gd \
               php8.2-mbstring php8.2-xml php8.2-zip php8.2-intl \
               php8.2-bcmath php8.2-imagick php8.2-redis php8.2-opcache
systemctl enable --now php8.2-fpm

# ---- 3. MySQL 8.0 ----
apt install -y mysql-server
mysql_secure_installation          # ★ 交互式：设 root 密码、删匿名用户、禁远程 root
systemctl enable --now mysql

# ---- 4. Redis ----
apt install -y redis-server
sed -i 's/^# requirepass .*/requirepass 你的强密码/' /etc/redis/redis.conf
sed -i 's/^bind .*/bind 127.0.0.1/' /etc/redis/redis.conf
systemctl enable --now redis-server

# ---- 5. certbot ----
apt install -y certbot python3-certbot-nginx
```

### 路径对照

| 内容 | 宝塔 | 标准 Debian/Ubuntu |
|---|---|---|
| Nginx 主配置 | `/www/server/nginx/conf/nginx.conf` | `/etc/nginx/nginx.conf` |
| 站点配置 | `/www/server/panel/vhost/nginx/` | `/etc/nginx/sites-available/` + `sites-enabled/` 软链 |
| php.ini (FPM) | `/www/server/php/82/etc/php.ini` | `/etc/php/8.2/fpm/php.ini` |
| FPM 池配置 | `/www/server/php/82/etc/php-fpm.d/www.conf` | `/etc/php/8.2/fpm/pool.d/www.conf` |
| FPM socket | `/tmp/php-cgi-82.sock` | `/run/php/php8.2-fpm.sock` |
| MySQL 配置 | `/etc/my.cnf` | `/etc/mysql/mysql.conf.d/mysqld.cnf` |
| 网站根目录 | `/www/wwwroot/域名/` | `/var/www/域名/` |
| 日志 | `/www/wwwlogs/` | `/var/log/nginx/` |
| PHP 用户 | `www` | `www-data` |
| 服务名 | `php-fpm-82` | `php8.2-fpm` |

### 建站

```bash
DOMAIN=example.com
mkdir -p /var/www/$DOMAIN
chown -R www-data:www-data /var/www/$DOMAIN

# 建库
mysql -u root -p <<SQL
CREATE DATABASE ${DOMAIN//./_} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DOMAIN//./_}'@'localhost' IDENTIFIED BY '强密码';
GRANT ALL ON ${DOMAIN//./_}.* TO '${DOMAIN//./_}'@'localhost';
FLUSH PRIVILEGES;
SQL

# 站点配置（把第 7.2 节那份贴进去，注意改 fastcgi_pass 的 socket 路径）
vim /etc/nginx/sites-available/$DOMAIN
ln -s /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 证书（certbot 会自动改 Nginx 配置并配好自动续期）
certbot --nginx -d $DOMAIN -d www.$DOMAIN
```

**手工部署的优势**：配置在标准位置，网上所有教程都能直接用；没有面板这一层攻击面；升级走系统包管理器。

**代价**：所有事情都要自己做，没有可视化的备份/监控/证书管理。

**建议**：自己长期维护的服务器用手工部署；交付给客户、需要客户自己能点两下的用宝塔。

---

## 25. 速查附录

### 关键路径

```
/www/server/panel/                       面板本体
/www/server/panel/vhost/nginx/           站点 Nginx 配置
/www/server/panel/vhost/rewrite/         伪静态规则
/www/server/nginx/conf/nginx.conf        Nginx 主配置
/www/server/php/82/etc/php.ini           PHP 配置
/www/server/php/82/etc/php-fpm.d/www.conf  FPM 进程池
/www/server/php/82/bin/php               PHP CLI
/etc/my.cnf                              MySQL 配置
/www/server/mysql/data/                  MySQL 数据
/www/server/redis/redis.conf             Redis 配置
/www/wwwroot/域名/                        网站根目录
/www/wwwlogs/                            所有日志
/www/backup/                             备份
```

### 核心参数对照表

| 参数 | 位置 | 2G | 4G | 8G | 16G |
|---|---|---|---|---|---|
| `worker_processes` | nginx.conf | auto | auto | auto | auto |
| `worker_connections` | nginx.conf | 10240 | 20480 | 51200 | 51200 |
| `pm.max_children` | www.conf | 8 | 20 | 45 | 90 |
| `pm.start_servers` | www.conf | 3 | 6 | 12 | 24 |
| `pm.min_spare_servers` | www.conf | 2 | 4 | 8 | 16 |
| `pm.max_spare_servers` | www.conf | 5 | 10 | 20 | 40 |
| `memory_limit` | php.ini | 128M | 256M | 512M | 512M |
| `opcache.memory_consumption` | php.ini | 128 | 192 | 256 | 512 |
| `innodb_buffer_pool_size` | my.cnf | 256M | 1G | 3G | 6G |
| `max_connections` | my.cnf | 100 | 200 | 400 | 800 |
| `maxmemory` (redis) | redis.conf | 128mb | 256mb | 512mb | 1gb |

（假设 PHP + MySQL + Nginx 都在同一台机器上；单进程按 90MB 估算。**上线后按第 8/9 节的方法实测调整**。）

### 症状 → 第一个该看的日志

| 症状 | 日志 |
|---|---|
| 502 | `/www/server/php/82/var/log/php-fpm.log` + 站点 error.log |
| 504 | `/www/wwwlogs/php-82-slow.log` |
| 500 / 白屏 | `/www/wwwlogs/php_error.log` + `wp-content/debug.log` |
| 数据库连不上 | `/www/server/mysql/*.err` |
| 静态文件 404 | 站点 `.error.log`（会打出实际尝试的路径） |
| 网站慢 | `php-82-slow.log` + `mysql-slow.log` |
| Nginx 起不来 | `nginx -t` 的输出 + `/www/wwwlogs/nginx_error.log` |
| 服务突然全挂 | `df -h`（磁盘满）+ `dmesg -T \| grep -i oom` |

### 改完配置要重启什么

| 改了 | 需要 |
|---|---|
| Nginx 配置 | `nginx -t && nginx -s reload` |
| Nginx 的 `user` / `worker_processes` / 监听端口 | `/etc/init.d/nginx restart` |
| php.ini | `/etc/init.d/php-fpm-82 reload` |
| php-fpm 池配置 | `/etc/init.d/php-fpm-82 restart`（reload 不够） |
| `.user.ini` | 等 300 秒，或重启 php-fpm |
| my.cnf | `/etc/init.d/mysqld restart`（★ 会中断服务） |
| redis.conf | `/etc/init.d/redis restart` |
| wp-config.php | 不需要，立即生效 |
| 主题/插件代码 | 不需要（除非 `opcache.validate_timestamps=0`） |

### 新服务器上线检查清单

```
□ 时区已设置
□ swap 已配置（内存 ≤ 4G）
□ 文件描述符上限已调整
□ SSH 已改端口、已禁密码登录、公钥已配置
□ 防火墙已配置，只开必要端口
□ fail2ban 已启用
□ 宝塔面板：端口已改、安全入口已开、已绑域名、已开 2FA、已上 SSL
□ phpMyAdmin 已卸载或已限制
□ MySQL 只监听 127.0.0.1
□ Redis 只监听 127.0.0.1 且设了密码
□ OPcache 已开启且参数合理
□ pm.max_children 已按实测内存计算
□ innodb_buffer_pool_size 已设置
□ 慢查询日志和 PHP 慢日志已开启
□ logrotate 已配置
□ 备份任务已配置且备份到异地
□ ★ 已做过一次完整的恢复演练
□ 外部监控已配置（不装在这台机器上）
```

### 延伸阅读

- Nginx 官方文档：https://nginx.org/en/docs/
- PHP-FPM 配置说明：https://www.php.net/manual/en/install.fpm.configuration.php
- MySQL 8.0 参考手册：https://dev.mysql.com/doc/refman/8.0/en/
- WP-CLI 命令手册：https://developer.wordpress.org/cli/commands/
- WordPress 强化指南：https://developer.wordpress.org/advanced-administration/security/hardening/
- SSL 配置生成器：https://ssl-config.mozilla.org/
- 宝塔官方论坛：https://www.bt.cn/bbs/

---

## 学习路径

**第 1 周 — 能把站跑起来**
第 1、2、3、4 节 → 装好一台加固过的服务器
第 10 节 → 从零建一个 WordPress 站
第 11 节 → 配上 HTTPS

**第 2 周 — 理解底下那一层**
第 5 节 → 搞清楚宝塔改了什么
第 6、7 节 → Nginx 配置，把第 7.2 节那份配置逐行读懂
第 13 节 → 权限

**第 3 周 — 调优**
第 8、9 节 → PHP-FPM 和 MySQL，按自己机器的实际内存算一遍参数
第 12 节 → 三层缓存全部配上，用第 20 节的方法压测对比

**第 4 周 — 运维**
第 14 节 → 配好备份，**并且做一次恢复演练**
第 17、18 节 → 把日志路径和排错流程记熟
第 16 节 → 关掉 WP-Cron，改系统 cron
第 19 节 → 安全加固全过一遍

**长期**
第 15 节在每次迁移时翻出来照做
第 18 节当作故障时的手册
第 25 节贴在手边
