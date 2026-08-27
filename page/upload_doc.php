<?php
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

Auth::requireUser();

$obra_id = isset($_REQUEST['obra_id']) ? (int)$_REQUEST['obra_id'] : 0;
$redirect = $_SERVER['HTTP_REFERER'] ?? 'gerenciar_obra.php?obra_id=' . $obra_id;

if ($obra_id <= 0) {
    $_SESSION['erro'] = 'Obra inválida ou não encontrada.';
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

try {
    Csrf::validate($_POST['_token'] ?? null);
} catch (Throwable $exception) {
    $_SESSION['erro'] = $exception->getMessage();
    header('Location: ' . $redirect);
    exit;
}

$nome_arquivo = trim($_POST['nome_arquivo'] ?? '');
$tipo_documento = trim($_POST['tipo_documento'] ?? 'Geral');

if ($nome_arquivo === '') {
    $_SESSION['erro'] = 'Informe o nome do arquivo.';
    header('Location: ' . $redirect);
    exit;
}

if (!isset($_FILES['arquivo']) || !is_array($_FILES['arquivo'])) {
    $_SESSION['erro'] = 'Nenhum arquivo enviado.';
    header('Location: ' . $redirect);
    exit;
}

$file = $_FILES['arquivo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $message = 'Erro ao enviar arquivo.';
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $message = 'O arquivo excede o tamanho máximo permitido.';
    } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
        $message = 'Nenhum arquivo selecionado.';
    }
    $_SESSION['erro'] = $message;
    header('Location: ' . $redirect);
    exit;
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension !== 'pdf') {
    $_SESSION['erro'] = 'Apenas arquivos PDF são permitidos.';
    header('Location: ' . $redirect);
    exit;
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if ($mimeType !== 'application/pdf') {
    $_SESSION['erro'] = 'O conteúdo enviado não é um PDF válido.';
    header('Location: ' . $redirect);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    $_SESSION['erro'] = 'O arquivo não pode ser maior que 10MB.';
    header('Location: ' . $redirect);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/obras/' . $obra_id;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    $_SESSION['erro'] = 'Falha ao criar pasta de upload.';
    header('Location: ' . $redirect);
    exit;
}

$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
$baseName = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $baseName);
$baseName = preg_replace('/\s+/', '_', trim($baseName));
if ($baseName === '') {
    $baseName = 'documento';
}

$targetName = $baseName . '_' . time() . '.' . $extension;
$targetPath = $uploadDir . '/' . $targetName;
$relativePath = 'uploads/obras/' . $obra_id . '/' . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    $_SESSION['erro'] = 'Falha ao salvar o arquivo no servidor.';
    header('Location: ' . $redirect);
    exit;
}

$stmt = $conn->prepare("INSERT INTO documentos_obras (obra_id, nome_arquivo, caminho_arquivo, tipo_documento) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    $_SESSION['erro'] = 'Erro no banco de dados: ' . $conn->error;
    header('Location: ' . $redirect);
    exit;
}

$stmt->bind_param('isss', $obra_id, $nome_arquivo, $relativePath, $tipo_documento);
if ($stmt->execute()) {
    $_SESSION['sucesso'] = 'Documento enviado com sucesso.';
} else {
    $_SESSION['erro'] = 'Erro ao registrar documento: ' . $stmt->error;
    @unlink($targetPath);
}
$stmt->close();

header('Location: ' . $redirect);
exit;
