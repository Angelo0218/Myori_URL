<?php
// 数据库连接字符串
define('DB_DSN', 'mysql:host=localhost;dbname=url;'); // MySQL示例 mysql:host=localhost;dbname=testdb;charset=utf8mb4
// 数据库用户名
define('DB_USER', 'urlapp');
// 数据库密码
define('DB_PASSWD', 'urlapp233');
// 生成短链接随机字符长度 默认 6 位 不超过 32 位
define('CODE_LENGTH', 6);



if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (empty($_POST['url'])) {
        json(-2, '地址不能為空！');
    }

    $url = $_POST['url'];

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        json(-3, '格式不正確！');
    }

    $db = db();
    $hash = md5($url);
    $domain = parse_url($url)['host'];
    $code = null;
    $data = null;

    if (is_domain_blocked($domain)) {
        json(-6, '該網域不在服務範圍內！');
    }

    if (empty($_POST['code'])) {
        $stmt = $db->prepare("select * from url where hash = ?");
        $stmt->execute([$hash]);
        $data = $stmt->fetchAll();
    } else {
        $code = $_POST['code'];
        $stmt = $db->prepare("select * from url where code = ?");
        $stmt->execute([$code]);
        $codeExist = $stmt->fetchAll();
        if ($codeExist) {
            if (current($codeExist)['hash'] == $hash) {
                $data[] = ['code' => current($codeExist)['code']];
            } else {
                json(-7, '该 Code 已占用，更换一个呗!');
            }
        }
    }

    if ($data) {
        $code = current($data)['code'];
    } else {
        if (empty($code)) {
            $code = generate_code();
        }
        $ip = get_client_ip();
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $stmt = $db->prepare("select * from url where code = ?");
        $stmt->execute([$code]);
        $exist = $stmt->fetchColumn();
        if ($exist) {
            json(-4, '天选之子，再来一次!');
        }

        $stmt = $db->prepare("insert into url(code, hash, url, ip, user_agent) values (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$code, $hash, $url, $ip, $user_agent]);
        if (!$result) {
            json(-5, '系统繁忙，请稍后再试!');
        }
    }

    $base_url = $_SERVER['HTTP_HOST'] .'/'. $code;
    json(0, 'OK',[
        'short' => $base_url,
        'generic' => 'https://' . $base_url,
        'long' => 'https://' . $base_url,
    ]);
} else if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    header("Content-Type: text/html");
    if ($_SERVER['REQUEST_URI'] != '/') {
        $code = substr($_SERVER['REQUEST_URI'], 1);
        $db = db();
        $stmt = $db->prepare("select * from url where code = ?");
        $stmt->execute([$code]);
        $data = $stmt->fetchAll();
        if ($data) {
            $url = current($data)['url'];
            $domain = parse_url($url)['host'];
            $url_id = current($data)['id'];
            $ip = get_client_ip();
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $visit_count = current($data)['visit_count'] + 1;

            $stmt = $db->prepare("update url set visit_count=? where id = ?");
            $stmt->execute([$visit_count, $url_id]);

            $stmt = $db->prepare("insert into visit_history(url_id, ip, user_agent) values (?, ?, ?)");
            $stmt->execute([$url_id, $ip, $user_agent]);

            if (is_domain_blocked($domain)) {
                http_response_code(403);
            } else {
                header("Location: {$url}");
            }
        } else {
            header("Location: /");
        }
        exit(0);
    }
}

function json($code, $msg, $data=[]) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data]);
    exit(0);
}

function db() {
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWD);
    } catch (PDOException $e) {
        json(-1, '数据库连接失败!'.$e->getMessage());
    }

    return $pdo;
}

function generate_code() {
    $seeds = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9));
    $depository = [];
    for ($i = 0; $i < CODE_LENGTH-1; $i++) {
        $depository = array_merge($depository, $seeds);
    }
    shuffle($depository);
    return join('', array_slice($depository, 0, CODE_LENGTH));
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function is_domain_blocked($domain) {
    $db = db();
    $stmt = $db->prepare("select * from blacklist");
    $stmt->execute();
    $blacklist = $stmt->fetchAll();

    foreach ($blacklist as $blocked_domain) {
        if (fnmatch($blocked_domain['domain'], $domain, FNM_CASEFOLD | FNM_NOESCAPE)) {
            return true;
        }
    }
    return false;
}

header("Content-Type: text/html");

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
     <meta charset="UTF-8">
     <title>公部門短網域｜苗栗國政府</title>
     <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0;">
     <meta name="format-detection" content="telephone=no">
     <link rel="icon" type="image/png" href="/img/logo.png">
     <link rel="stylesheet" type="text/css" href="/main.css">
     <link rel="preconnect" href="https://fonts.googleapis.com"> 
     <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> 
     <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC&display=swap" rel="stylesheet">
     <link rel="preconnect" href="https://fonts.googleapis.com">
     <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
     <link rel="stylesheet" href="https://at.alicdn.com/t/c/font_3948736_5826ppfenst.css">
     <link rel="stylesheet" type="text/css" href="/main.css">
     <style>
        * {
            padding: 0;
            margin: 0;
            box-sizing: border-box;
        }
        body {
            display:flex;
            justify-content:center;
            align-items: center;
            height: 100vh;
            color: #333
        }
        a {
            font-size: .8em;
            width: auto;
            color: #999;
        }
        .site-header {
            margin-bottom: 14px;
            margin-top: -140px;
            text-align: center;
        }
        .main-title {
            font-size: 1.8em;
        }
        .subtitle {
            font-size: 1em;
            color: #bbb;
            font-weight: 400;
            margin: 4px 0 10px 0;
        }
        .site-body {
            margin: 24px 0 24px;
        }
        .site-body-item {
            margin: 22px 0 22px 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .site-input {
            border-radius: 3px;
            padding: 5px;
            font-size: 14px;
            line-height: 1.2;
            border: 1px solid #ccc;
            width: 280px;
            box-sizing: border-box;
        }
        .site-button {
            background-color: #fff;
            color: #333;
            text-shadow: 0 1px 0 #fff;
            text-decoration: none;
            font-weight: 700;
            padding: 4px 15px 3px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 14px;
            font-family: Arial,sans-serif;
            display: inline-block;
            line-height: 1.25;
            outline: 0;
            margin-left: 4px;
        }
        .site-button:hover {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            color: #333;
            text-shadow: 0 1px 0 #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .site-footer {
            text-align: center;
            font-size: 12px;
            color: #bbb;
        }
    </style>
     <!--一般-->
     <meta property="og:title" content="公部門短網域｜苗栗國政府"/>
     <meta property="og:type" content="website"/>
     <meta property="og:url" content="https://url.myori.org"/>
     <meta property="og:image" content="https://url.myori.org/img/logo.png"/>
     <!--Open Graph-->
 </head>
<!--head-->
<main>
    <div class="container">
        <div class="site-header">
            <h1 class="main-title">苗栗國公務部門URL轉換</h1>
            <h2 class="subtitle">統一東亞從統一URL開始！</h2>
        </div>
        <div class="site-body">
            <div class="site-body-item">
                <input type="text" name="url" class="site-input"/>
                <div id="submit" class="site-button"><i class="iconfont icon-exchange"></i></div>
            </div>
            <div class="site-body-item">
                <input type="text" name="short_url" class="site-input" />
                <div id="copy" class="site-button"><i class="iconfont icon-copy"></i></div>
            </div>
        </div>
        <div class="site-footer">
            <p>&copy苗栗國政府｜數位省</a></p>
        </div>
    </div>
<main>
    <script>
        $('#submit').click(function () {
            var url = $('input[name=url]').val();

            if (url == '') {
                alert("链接不能为空！");
                return;
            }

            if (!/^http(s)?:\/\//.test(url)) {
                alert("链接格式不正确！");
                return;
            }

            $.post("/", {url: url}, function (data) {
                if (data.code == 0) {
                    $('input[name=short_url]').val(data.data.generic);
                } else {
                    alert(data.msg);
                }
            }, 'json')
        });

        $('#copy').click(function () {
            $("input[name=short_url]").focus();
            $("input[name=short_url]").select();
            document.execCommand("Copy");
        });
    </script>
</body>
</html>