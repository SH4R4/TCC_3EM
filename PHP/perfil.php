<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');

permitirMetodos(["GET", "PUT", "DELETE"]);

// Verifica token JWT
$usuario = verificarToken($jwtSecretKey);

// =======================
// GET: Obter dados do perfil
// =======================
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.nome,
                u.altura,
                u.peso_inicial,
                u.peso,
                u.imc,
                u.imc_inicial,
                u.data_nascimento,
                p.pergunta6_tipo_dieta,
                p.pergunta8_disturbios
            FROM usuarios u
            JOIN perguntas p ON u.perguntas_id = p.id
            WHERE u.id = :id
        ");
        $stmt->bindParam(":id", $usuario->id);
        $stmt->execute();
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dados) {
            enviarErro(404, "Perfil não encontrado.");
        }

        // Se a pergunta estiver nula, usa "Nenhuma" como fallback
        $dados["pergunta6_tipo_dieta"] = !empty($dados["pergunta6_tipo_dieta"]) ? $dados["pergunta6_tipo_dieta"] : "nenhuma";

        // Se peso estiver nulo, usa peso_inicial como fallback
        $dados["peso"] = $dados["peso"] ?? $dados["peso_inicial"];

        // Se imc estiver nulo, usa imc_inicial como fallback
        $dados["imc"] = $dados["imc"] ?? $dados["imc_inicial"];

        // Calcular idade
        $dataNascimento = new DateTime($dados["data_nascimento"]);
        $hoje = new DateTime();
        $idade = $dataNascimento->diff($hoje)->y;

        // Formatar tipo de dieta
        $tipoDieta = (!empty($dados["pergunta6_tipo_dieta"]) && strtolower($dados["pergunta6_tipo_dieta"]) !== "nenhuma") 
            ? $dados["pergunta6_tipo_dieta"] 
            : "Nenhum tipo de dieta foi registrado.";

        // Formatar restrições alimentares (distúrbios)
        if (!empty($dados["pergunta8_disturbios"]) && strtolower($dados["pergunta8_disturbios"]) !== "nenhum") {
            // Mapear nomes técnicos para nomes formatados
            $mapeamentoDisturbios = [
                'celíaca' => 'Celíaca',
                'diabetes' => 'Diabetes',
                'hipercolesterolemia' => 'Hipercolesterolemia',
                'hipertensão' => 'Hipertensão',
                'sii' => 'SII (Síndrome do Intestino Irritável)',
                'intolerancia_lactose' => 'Intolerância à Lactose' // ← ADICIONAR
            ];
            
            // Separar os distúrbios por vírgula
            $disturbiosArray = array_map('trim', explode(',', $dados["pergunta8_disturbios"]));
            $disturbiosFormatados = [];
            
            foreach ($disturbiosArray as $disturbio) {
                $disturbioLower = strtolower($disturbio);
                if (isset($mapeamentoDisturbios[$disturbioLower])) {
                    $disturbiosFormatados[] = $mapeamentoDisturbios[$disturbioLower];
                } else {
                    // Se não estiver no mapeamento, capitaliza a primeira letra
                    $disturbiosFormatados[] = ucfirst($disturbio);
                }
            }
            
            $restricoesAlimentares = implode(', ', $disturbiosFormatados);
        } else {
            $restricoesAlimentares = "Nenhuma restrição alimentar foi registrada.";
        }

        enviarSucesso(200, [
        "mensagem" => "Dados do perfil carregados com sucesso!",
            "nome" => $dados["nome"],
            "altura" => $dados["altura"],
            "peso" => $dados["peso"],
            "imc" => $dados["imc"],
            "idade" => $idade,
            "data_nascimento" => $dados["data_nascimento"],
            "tipo_dieta" => $dados["pergunta6_tipo_dieta"], 
            "disturbios" => $dados["pergunta8_disturbios"], 
            "restricoes_alimentares" => $restricoesAlimentares
        ]);
    } catch (PDOException $e) {
        enviarErro(500, "Erro ao buscar perfil: " . $e->getMessage());
    }
}

// =======================
// PUT: Atualizar dados do perfil
// =======================
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        // Campos obrigatórios
        if (empty($data["nome"]) || empty($data["data_nascimento"]) || empty($data["altura"])) {
            enviarErro(400, "Nome, data de nascimento e altura são obrigatórios.");
        }

        $nome = $data["nome"];
        $dataNascimento = $data["data_nascimento"];
        $altura = $data["altura"];
        
        // Campos opcionais
        $tipoDieta = $data["tipo_dieta"] ?? null;
        $disturbios = $data["disturbios"] ?? null;
        $senhaAtual = $data["senha_atual"] ?? null;
        $novaSenha = $data["nova_senha"] ?? null;

        // Buscar peso atual para calcular IMC
        $stmtPeso = $pdo->prepare("SELECT peso, peso_inicial FROM usuarios WHERE id = :id");
        $stmtPeso->execute([":id" => $usuario->id]);
        $pesoData = $stmtPeso->fetch(PDO::FETCH_ASSOC);
        $peso = $pesoData["peso"] ?? $pesoData["peso_inicial"];

        // Calcular IMC com a nova altura
        $alturaMetros = $altura / 100;
        $imc = ($alturaMetros > 0) ? $peso / ($alturaMetros * $alturaMetros) : null;

        // 1. Atualizar tabela usuarios (incluindo IMC recalculado)
        $updateUsuarios = "UPDATE usuarios SET nome = :nome, data_nascimento = :data_nascimento, altura = :altura, imc = :imc";
        $paramsUsuarios = [
            ":nome" => $nome,
            ":data_nascimento" => $dataNascimento,
            ":altura" => $altura,
            ":imc" => $imc,
            ":id" => $usuario->id
        ];

        // Se está mudando senha
        if (!empty($novaSenha)) {
            // Verificar senha atual
            $stmtVerifica = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
            $stmtVerifica->execute([":id" => $usuario->id]);
            $senhaHash = $stmtVerifica->fetchColumn();

            if (!password_verify($senhaAtual, $senhaHash)) {
                enviarErro(401, "Senha atual incorreta.");
            }

            $updateUsuarios .= ", senha = :senha";
            $paramsUsuarios[":senha"] = password_hash($novaSenha, PASSWORD_DEFAULT);
        }

        $updateUsuarios .= " WHERE id = :id";

        $stmtUsuarios = $pdo->prepare($updateUsuarios);
        $stmtUsuarios->execute($paramsUsuarios);

        // 2. Buscar perguntas_id do usuário
        $stmtPerguntasId = $pdo->prepare("SELECT perguntas_id FROM usuarios WHERE id = :id");
        $stmtPerguntasId->execute([":id" => $usuario->id]);
        $perguntasId = $stmtPerguntasId->fetchColumn();

        if (!$perguntasId) {
            enviarErro(404, "Perguntas não encontradas para este usuário.");
        }

        // 3. Atualizar tabela perguntas (sempre atualiza ambos os campos)
        $precisaReprocessar = false;

        if ($tipoDieta !== null || $disturbios !== null) {
            // Buscar valores atuais primeiro
            $stmtAtual = $pdo->prepare("SELECT pergunta6_tipo_dieta, pergunta8_disturbios FROM perguntas WHERE id = :id");
            $stmtAtual->execute([":id" => $perguntasId]);
            $dadosAtuais = $stmtAtual->fetch(PDO::FETCH_ASSOC);

            $tipoDietaFinal = $tipoDieta ?? $dadosAtuais["pergunta6_tipo_dieta"] ?? 'nenhuma';
            $disturbiosFinal = $disturbios ?? $dadosAtuais["pergunta8_disturbios"] ?? 'nenhum';

            $updatePerguntas = "UPDATE perguntas SET pergunta6_tipo_dieta = :tipo_dieta, pergunta8_disturbios = :disturbios WHERE id = :id";
            
            $stmtPerguntas = $pdo->prepare($updatePerguntas);
            $stmtPerguntas->execute([
                ":tipo_dieta" => $tipoDietaFinal,
                ":disturbios" => $disturbiosFinal,
                ":id" => $perguntasId
            ]);

            $precisaReprocessar = true;
        }

        // 4. Reprocessar alimentos se necessário
        if ($precisaReprocessar) {
            require_once(__DIR__ . DIRECTORY_SEPARATOR . 'alimentos' . DIRECTORY_SEPARATOR . 'alimentos_filtros.php');
            
            $stmtBusca = $pdo->prepare("SELECT pergunta6_tipo_dieta, pergunta8_disturbios FROM perguntas WHERE id = :id");
            $stmtBusca->execute([":id" => $perguntasId]);
            $dadosNovos = $stmtBusca->fetch(PDO::FETCH_ASSOC);
            
            $tipoDietaNova = strtolower($dadosNovos["pergunta6_tipo_dieta"] ?? 'nenhuma');
            $disturbiosNovos = strtolower($dadosNovos["pergunta8_disturbios"] ?? 'nenhum');
            
            $condicoes = aplicarFiltros($tipoDietaNova, $disturbiosNovos);
            $where = count($condicoes) ? "WHERE " . implode(" AND ", $condicoes) : "";
            $query = "SELECT id FROM alimentos $where";
            $stmtAlimentos = $pdo->query($query);
            $alimentosNovos = $stmtAlimentos->fetchAll(PDO::FETCH_COLUMN);
            
            $stmtDeleteAntigos = $pdo->prepare("DELETE FROM alimentos_permitidos WHERE usuario_id = :usuario_id");
            $stmtDeleteAntigos->execute([":usuario_id" => $usuario->id]);
            
            if (!empty($alimentosNovos)) {
                $stmtInsertNovo = $pdo->prepare("INSERT INTO alimentos_permitidos (usuario_id, alimento_id) VALUES (:usuario_id, :alimento_id)");
                foreach ($alimentosNovos as $alimentoId) {
                    $stmtInsertNovo->execute([":usuario_id" => $usuario->id, ":alimento_id" => $alimentoId]);
                }
            }
            
            $stmtLimparDieta = $pdo->prepare("
                DELETE FROM dieta 
                WHERE usuario_id = :usuario_id 
                AND alimento_id NOT IN (
                    SELECT alimento_id FROM alimentos_permitidos WHERE usuario_id = :usuario_id2
                )
            ");
            $stmtLimparDieta->execute([
                ":usuario_id" => $usuario->id,
                ":usuario_id2" => $usuario->id
            ]);
        }

        // 5. Buscar dados atualizados para retornar
        $stmtRetorno = $pdo->prepare("
            SELECT 
                u.nome,
                u.altura,
                u.peso,
                u.imc,
                u.data_nascimento,
                p.pergunta6_tipo_dieta,
                p.pergunta8_disturbios
            FROM usuarios u
            JOIN perguntas p ON u.perguntas_id = p.id
            WHERE u.id = :id
        ");
        $stmtRetorno->execute([":id" => $usuario->id]);
        $dadosAtualizados = $stmtRetorno->fetch(PDO::FETCH_ASSOC);

        enviarSucesso(200, [
            "mensagem" => "Perfil atualizado com sucesso!",
            "nome" => $dadosAtualizados["nome"],
            "altura" => $dadosAtualizados["altura"],
            "peso" => $dadosAtualizados["peso"],
            "imc" => $dadosAtualizados["imc"],
            "data_nascimento" => $dadosAtualizados["data_nascimento"],
            "tipo_dieta" => $dadosAtualizados["pergunta6_tipo_dieta"],
            "disturbios" => $dadosAtualizados["pergunta8_disturbios"]
        ]);
    } catch (PDOException $e) {
        enviarErro(500, "Erro ao atualizar perfil: " . $e->getMessage());
    }
}

// =======================
// DELETE: Desativar conta
// =======================
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        // Verificar senha antes de deletar
        if (empty($data["senha"])) {
            enviarErro(400, "Senha é obrigatória para deletar a conta.");
        }

        $stmtVerifica = $pdo->prepare("SELECT senha FROM usuarios WHERE id = :id");
        $stmtVerifica->execute([":id" => $usuario->id]);
        $senhaHash = $stmtVerifica->fetchColumn();

        if (!password_verify($data["senha"], $senhaHash)) {
            enviarErro(401, "Senha incorreta.");
        }

        // Desativar conta
        $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id");
        $stmt->execute([":id" => $usuario->id]);

        enviarSucesso(200, [
            "mensagem" => "Conta desativada com sucesso!"
        ]);
    } catch (PDOException $e) {
        enviarErro(500, "Erro ao desativar conta: " . $e->getMessage());
    }
}
?>