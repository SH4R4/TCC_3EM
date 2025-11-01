<?php
// Configurações de CORS
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Responder requisições OPTIONS (preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("HTTP/1.1 200 OK");
    exit();
}

// Importa configurações do 'config.php'
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

permitirMetodos(["GET", "POST"]);

$requestMethod = $_SERVER["REQUEST_METHOD"];
$data = json_decode(file_get_contents("php://input"), true);

// =======================
// POST: Cadastro do usuário
// =======================
if ($requestMethod === "POST" && isset($_GET["endpoint"])) {
    $endpoint = $_GET["endpoint"];

    if ($endpoint === "cadastro") {
        if (!empty($data["nome"]) && !empty($data["email"]) && !empty($data["senha"])) {
            if (strlen($data["senha"]) < 6) {
                enviarErro(400, "A senha deve ter pelo menos 6 caracteres.");
            }

            $stmt = $pdo->prepare("SELECT email FROM usuarios WHERE email = :email");
            $stmt->bindParam(":email", $data["email"]);
            $stmt->execute();

            if ($stmt->fetch()) {
                enviarErro(409, "Usuário já existente.");
            }

            $senhaHash = password_hash($data["senha"], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (:nome, :email, :senha)");
            $stmt->bindParam(":nome", $data["nome"]);
            $stmt->bindParam(":email", $data["email"]);
            $stmt->bindParam(":senha", $senhaHash);

            if ($stmt->execute()) {
                $userId = $pdo->lastInsertId();
                
                $payload = [
                    "id" => $userId,
                    "email" => $data["email"],
                    "nome" => $data["nome"],
                    "termos_aceitos" => false,
                    "essenciais_completas" => false,
                    "perguntas_completas" => false,
                    "exp" => time() + (60 * 60 * 24)
                ];
                $jwt = gerarToken($payload, $jwtSecretKey);

                enviarSucesso(201, [
                    "mensagem" => "Usuário criado com sucesso!",
                    "id" => $userId,
                    "token" => $jwt,
                    "termos_aceitos" => false,
                    "essenciais_completas" => false,
                    "perguntas_completas" => false
                ]);
            } else {
                enviarErro(500, "Erro ao cadastrar usuário.");
            }
        } else {
            enviarErro(400, "Dados inválidos.");
        }

// =======================
// POST: Login do usuário
// =======================
    } elseif ($endpoint === "login") {
        if (!empty($data["email"]) && !empty($data["senha"])) {
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    nome, 
                    email, 
                    senha, 
                    perguntas_id, 
                    COALESCE(termos_aceitos, 0) as termos_aceitos,
                    sexo_biologico, 
                    data_nascimento, 
                    altura, 
                    peso_inicial 
                FROM usuarios 
                WHERE email = :email AND ativo = 1
            ");
            $stmt->bindParam(":email", $data["email"]);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($data["senha"], $usuario["senha"])) {
                // Converter para boolean
                $termosAceitos = (bool)$usuario["termos_aceitos"];

                // Verificar se completou essenciais
                $essenciaisCompletas = !is_null($usuario["sexo_biologico"]) 
                    && !is_null($usuario["data_nascimento"]) 
                    && !is_null($usuario["altura"]) 
                    && !is_null($usuario["peso_inicial"]);

                // Verificar se completou perguntas
                $perguntasCompletas = !is_null($usuario["perguntas_id"]);

                // Montar payload do JWT
                $payload = [
                    "id" => $usuario["id"],
                    "email" => $usuario["email"],
                    "nome" => $usuario["nome"],
                    "termos_aceitos" => $termosAceitos,
                    "essenciais_completas" => $essenciaisCompletas,
                    "perguntas_completas" => $perguntasCompletas,
                    "exp" => time() + (60 * 60 * 24)
                ];

                // Gerar JWT
                $jwt = gerarToken($payload, $jwtSecretKey);

                // Responder
                enviarSucesso(200, [
                    "mensagem" => "Login bem-sucedido!",
                    "id" => $usuario["id"],
                    "token" => $jwt,
                    "termos_aceitos" => $termosAceitos,
                    "essenciais_completas" => $essenciaisCompletas,
                    "perguntas_completas" => $perguntasCompletas
                ]);
            } else {
                enviarErro(401, "Email ou senha incorretos.");
            }
        } else {
            enviarErro(400, "Dados inválidos.");
        }
    }

    exit();
}
?>