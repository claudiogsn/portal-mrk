<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';

    $url = "https://vemprodeck.com.br/crm/engine.php?class=LoginForm&method=onLogin&static=1";

    $postData = http_build_query([
        'login' => $login,
        'password' => $password,
        'previous_class' => '',
        'previous_method' => '',
        'previous_parameters' => ''
    ]);

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            "accept: */*",
            "accept-language: pt-BR,pt;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6,vi;q=0.5",
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded; charset=UTF-8",
            "pragma: no-cache",
            "sec-ch-ua: \"Chromium\";v=\"128\", \"Not;A=Brand\";v=\"24\", \"Microsoft Edge\";v=\"128\"",
            "sec-ch-ua-mobile: ?0",
            "sec-ch-ua-platform: \"Windows\"",
            "sec-fetch-dest: empty",
            "sec-fetch-mode: cors",
            "sec-fetch-site: same-origin",
            "x-requested-with: XMLHttpRequest"
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    $curlResponse = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    header('Content-Type: application/json');

    if ($http_code === 200) {
        if (strpos($curlResponse, "__adianti_error('Erro', 'Usuário não encontrado ou senha incorreta'") !== false) {
            $error_message = 'Usuário não encontrado ou senha incorreta';
            $_SESSION['error_message'] = $error_message;
            echo json_encode(['error' => $error_message]);
            http_response_code(401);
        } elseif (strpos($curlResponse, "setTimeout( function() { __adianti_goto_page('index.php?class=EmptyPage');") !== false) {
            $_SESSION['user'] = $login;
            echo json_encode(['message' => 'Login bem-sucedido']);
            http_response_code(200);
        } else {
            $error_message = 'Erro inesperado na autenticação';
            $_SESSION['error_message'] = $error_message;
            echo json_encode(['error' => $error_message]);
            http_response_code(500);
        }
    } else {
        $error_message = 'Falha na comunicação com o servidor';
        $_SESSION['error_message'] = $error_message;
        echo json_encode(['error' => $error_message]);
        http_response_code(502);
    }
}
?>
