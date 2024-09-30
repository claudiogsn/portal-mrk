<?php
// Verifica se o hostname não é 'localhost'
$host = $_SERVER['HTTP_HOST'];
if ($host !== 'localhost') {
    $url = "https://portal.mrksolucoes.com.br/external/realizarBalanco.html?tag=";
} else {
    $url = "http://localhost/portal-mrk/external/realizarBalanco.html?tag=";
}

// Verifica se a tag foi passada como parâmetro
if (isset($_GET['tag'])) {
    $tag = $_GET['tag'];
    $url .= urlencode($tag);
} else {
    // Caso a tag não tenha sido passada, exibe uma mensagem de erro
    echo "Tag não fornecida. Por favor, forneça uma tag válida.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Realziar Balanço</title>
    <style>
        /* O iframe ocupará toda a tela */
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
        }

        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Carrega a URL dentro do iframe -->
    <iframe src="<?php echo htmlspecialchars($url); ?>"></iframe>
</body>
</html>
