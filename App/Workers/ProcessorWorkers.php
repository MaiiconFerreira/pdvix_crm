<?php
// Arquivo: App/Workers/ProcessorWorkers.php

namespace App\Workers;

use App\Models\ConferenciasModel;
// Importação correta para a versão 2.x
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;

class ProcessorWorkers {

    private ConferenciasModel $model;
    private string $logFile;
    private string $googleLogDir;
    private string $googleJsonPath;

    public function __construct() {
        $this->model = new ConferenciasModel();

        // Configuração de caminhos de Log
        $this->logFile = PATH_LOGS . 'workers_conferencia.log';
        $this->googleLogDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/google_cloud_vision/';

        $this->checkDirectories();

        // Caminho para o seu arquivo de credenciais
        $this->googleJsonPath = __DIR__ . '/../../google-key.json';
    }

    private function log(string $msg, bool $isGoogle = false): void {
        $date = date('Y-m-d H:i:s');
        $line = "[$date] $msg" . PHP_EOL;

        echo $line;

        file_put_contents($this->logFile, $line, FILE_APPEND);

        if ($isGoogle) {
            $specificLog = $this->googleLogDir . 'vision_history_' . date('Y-m-d') . '.log';
            file_put_contents($specificLog, $line, FILE_APPEND);
        }
    }

    private function checkDirectories(): void {
        $paths = [
            PATH_LISTAS_PENDENTES_CONVERSAO_PNG,
            PATH_LISTAS_PENDENTES_PROCESSAMENTO_TE,
            PATH_LISTAS_PROCESSADAS,
            PATH_LISTAS_ORIGINAIS,
            PATH_LISTAS_ERROR_CONVERTPNG,
            PATH_LISTAS_ERROR_TE,
            $this->googleLogDir
        ];

        foreach ($paths as $p) {
            if (!is_dir($p)) {
                mkdir($p, 0777, true);
            }
        }
    }

    private function getIdFromFilename(string $filename): ?int {
        return preg_match('/CONF_(\d+)_/', $filename, $m) ? (int)$m[1] : null;
    }

    private function isWindows(): bool {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    public function runPdfToPng(): void {
    $files = glob(PATH_LISTAS_PENDENTES_CONVERSAO_PNG . '/*.{pdf,png,jpg,jpeg}', GLOB_BRACE);

    foreach ($files as $file) {
        $filename = basename($file);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $this->log(">>> Arquivo detectado: $filename");

        // Destino temporário para processamento OCR
        $outputPath = PATH_LISTAS_PENDENTES_PROCESSAMENTO_TE . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.png';

        // LÓGICA DE CONVERSÃO
        if ($ext === 'pdf') {
            $this->log("Convertendo PDF para PNG (Otimizado)...");
            // Usamos density 150 e grayscale para garantir que fique abaixo de 1MB para o OCR.space
            $cmd = $this->isWindows()
                ? "magick convert -density 150 \"$file\" -background white -alpha remove -colorspace gray -quality 60 -append \"$outputPath\""
                : "convert -density 150 \"$file\" -background white -alpha remove -colorspace gray -quality 60 -append \"$outputPath\"";

            exec($cmd . ' 2>&1', $output, $code);
        } else {
            $this->log("Arquivo de imagem detectado. Aplicando pré-processamento PB...");
            // Se já for imagem, forçamos a redução de cores para garantir o limite de 1024KB do OCR.space
            $cmd = $this->isWindows()
                ? "magick convert \"$file\" -colorspace gray -threshold 50% \"$outputPath\""
                : "convert \"$file\" -colorspace gray -threshold 50% \"$outputPath\"";

            exec($cmd . ' 2>&1', $output, $code);
        }

        if ($code === 0 && file_exists($outputPath)) {
            $this->log("Sucesso no pré-processamento.");
            rename($file, PATH_LISTAS_ORIGINAIS . "/$filename");
        } else {
            $this->log("ERRO no processamento: " . implode(' | ', $output));
            rename($file, PATH_LISTAS_ERROR_CONVERTPNG . "/$filename");
        }
        return;
    }
}

    /* ==========================
     * WORKER 2 — CLOUD VISION OCR (Atualizado para v2.x)
     * ========================== */
    public function runCloudVisionOCR(): void {
        $files = glob(PATH_LISTAS_PENDENTES_PROCESSAMENTO_TE . '/*.{png,jpg,jpeg}', GLOB_BRACE);

        foreach ($files as $file) {
            $filename = basename($file);
            $id = $this->getIdFromFilename($filename);

            $this->log("---------------------------------------------------");
            $this->log(">>> [ETAPA 2] INICIANDO OCR CLOUD: $filename", true);

            if (!file_exists($this->googleJsonPath)) {
                $this->log("ERRO CRÍTICO: Arquivo JSON de credenciais não encontrado em: " . $this->googleJsonPath, true);
                return;
            }

            try {
                $this->log("Conectando à Google Cloud Vision API (v2)...", true);

                $client = new ImageAnnotatorClient([
                    'credentials' => $this->googleJsonPath
                ]);

                $imageContent = file_get_contents($file);
                $this->log("Enviando imagem (" . round(strlen($imageContent)/1024) . " KB) para análise...", true);

                // 1. Prepara a Feature e a Imagem
                $feature = (new \Google\Cloud\Vision\V1\Feature())->setType(\Google\Cloud\Vision\V1\Feature\Type::DOCUMENT_TEXT_DETECTION);
                $image = (new \Google\Cloud\Vision\V1\Image())->setContent($imageContent);

                // 2. Cria a requisição individual (AnnotateImageRequest)
                $request = (new \Google\Cloud\Vision\V1\AnnotateImageRequest())
                    ->setFeatures([$feature])
                    ->setImage($image);

                // 3. NOVIDADE: Cria o objeto de lote (BatchAnnotateImagesRequest) exigido pela v2
                $batchRequest = (new \Google\Cloud\Vision\V1\BatchAnnotateImagesRequest())
                    ->setRequests([$request]);

                // 4. Envia o objeto BatchAnnotateImagesRequest
                $batchResponse = $client->batchAnnotateImages($batchRequest);
                $responses = $batchResponse->getResponses();
                $response = $responses[0];

                if ($error = $response->getError()) {
                    throw new \Exception("Erro da API Google: " . $error->getMessage());
                }

                $annotation = $response->getFullTextAnnotation();

                if ($annotation) {
                    $textSnippet = mb_substr($annotation->getText(), 0, 100);
                    $this->log("API Respondeu! Texto detectado (início): " . str_replace("\n", " ", $textSnippet) . "...", true);

                    $basePath = PATH_LISTAS_PROCESSADAS . '/' . pathinfo($filename, PATHINFO_FILENAME);

                    // Salva TXT
                    file_put_contents("$basePath.txt", $annotation->getText());
                    $this->log("Arquivo TXT salvo.");

                    // Processa CSV
                    $this->log("Iniciando extração estruturada para CSV...");
                    $this->parseVisionToCsv($annotation, "$basePath.csv");
                    $this->log("Arquivo CSV gerado com sucesso.");

                    if ($id) {
                        $this->model->atualizarCaminhoEStatus($id, "$basePath.csv", 'processado', 'caminhofull_lista_txt');
                        $this->log("Banco de dados atualizado para ID: $id");
                    }

                    rename($file, PATH_LISTAS_ORIGINAIS . "/ocr_$filename");
                    $this->log("Processo concluído para $filename", true);
                } else {
                    $this->log("Aviso: Google não detectou nenhum texto nesta imagem.", true);
                    rename($file, PATH_LISTAS_ERROR_TE . "/$filename");
                }

                $client->close();

            } catch (\Exception $e) {
                $this->log("!!! ERRO NO PROCESSO GOOGLE VISION: " . $e->getMessage(), true);
                rename($file, PATH_LISTAS_ERROR_TE . "/$filename");
            }

            $this->log("---------------------------------------------------");
            gc_collect_cycles();
            return;
        }
    }

    /* ==========================
     * WORKER 2 — OCR.SPACE (Alternativa Gratuita)
     * ========================== */
    public function runOcrSpace(): void {
        // Busca arquivos na pasta de processamento
        $files = glob(PATH_LISTAS_PENDENTES_PROCESSAMENTO_TE . '/*.{png,jpg,jpeg}', GLOB_BRACE);

        foreach ($files as $file) {
            $filename = basename($file);
            $id = $this->getIdFromFilename($filename);

            $this->log("---------------------------------------------------");
            $this->log(">>> [ETAPA 2] INICIANDO OCR.SPACE: $filename");

            try {
                $apiKey = 'K81925332788957'; // Sua chave atual

                $this->log("Enviando para OCR.space (Engine 2)...");

                $postData = [
                    'apikey' => $apiKey,
                    'language' => 'por',
                    'isOverlayRequired' => 'false',
                    'isTable' => 'true', // Ativa o reconhecimento de tabelas
                    'file' => new \CURLFile($file),
                    'OCREngine' => '2',
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.ocr.space/parse/image');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30s

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200) {
                    throw new \Exception("Erro na requisição API: HTTP $httpCode");
                }

                $result = json_decode($response, true);

                if (isset($result['ParsedResults'][0]['ParsedText'])) {
                    $text = $result['ParsedResults'][0]['ParsedText'];
                    $this->log("Sucesso! Texto extraído.");

                    $basePath = PATH_LISTAS_PROCESSADAS . '/' . pathinfo($filename, PATHINFO_FILENAME);

                    // 1. Salva o Log/TXT bruto
                    file_put_contents("$basePath.txt", $text);

                    // 2. Processa o CSV (usando o texto extraído do OCR.space)
                    $this->log("Gerando CSV estruturado...");
                    $this->parseTextToCsv($text, "$basePath.csv");

                    // 3. Atualiza Banco de Dados
                    if ($id) {
                        $this->model->atualizarCaminhoEStatus($id, "$basePath.csv", 'processado', 'caminhofull_lista_txt');
                        $this->log("Banco atualizado para ID: $id");
                    }

                    // 4. Move o arquivo original para a pasta de processados
                    rename($file, PATH_LISTAS_ORIGINAIS . "/ocr_space_$filename");
                    $this->log("Processo concluído com sucesso.");

                } else {
                    $errorMsg = $result['ErrorMessage'][0] ?? 'Erro desconhecido na API';
                    throw new \Exception($errorMsg);
                }

            } catch (\Exception $e) {
                $this->log("!!! ERRO NO OCR.SPACE: " . $e->getMessage());
                rename($file, PATH_LISTAS_ERROR_TE . "/$filename");
            }

            $this->log("---------------------------------------------------");
            return; // Processa um por ciclo para não sobrecarregar
        }
    }

    /**
     * Novo Parser adaptado para o formato de texto do OCR.space
     */
    private function parseTextToCsv(string $text, string $csvPath): void {
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['Data', 'Entrada', 'Saida', 'Nome', 'CPF', 'Assinatura']);

        $lines = explode("\n", $text);
        $count = 0;

        foreach ($lines as $line) {
            // Regex ajustada para o formato: 13/01/2026 22:00 06:00 Nome CPF
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})\s+(\d{2}:\d{2})\s+(.*?)\s+(\d{3}[\d\s.-]+)/', $line, $m)) {
                $data = $m[1];
                $ent = $m[2];
                $sai = $m[3];
                $nome = trim($m[4]);
                $cpf = trim($m[5]);

                fputcsv($fp, [$data, $ent, $sai, $nome, $cpf, 'Detectado']);
                $count++;
            }
        }
        fclose($fp);
        $this->log("CSV: $count linhas extraídas.");
    }

    private function parseVisionToCsv($annotation, $csvPath): void {
        $fp = fopen($csvPath, 'w');
        fputcsv($fp, ['Data', 'Entrada', 'Saida', 'Nome', 'CPF', 'Assinatura']);

        $linhasEncontradas = 0;

        foreach ($annotation->getPages() as $page) {
            foreach ($page->getBlocks() as $block) {
                foreach ($block->getParagraphs() as $paragraph) {
                    $words = [];
                    foreach ($paragraph->getWords() as $word) {
                        $text = "";
                        foreach ($word->getSymbols() as $symbol) {
                            $text .= $symbol->getText();
                        }
                        $words[] = $text;
                    }

                      $lineText = implode(' ', $words);

                      // 1. Regex mais flexível para Data (aceita erros comuns de OCR no ano ou separadores)
                      // 2. Captura Data, Entrada, Saída e o resto da linha
                      if (preg_match('/(\d{2}[\/\d]\d{2}[\/\d]\d{2,4})\s+(\d{2}:\d{2})\s+(\d{2}:\d{2})(.*)/', $lineText, $matches)) {
                          $dataRaw = $matches[1];
                          $entrada = $matches[2];
                          $saida   = $matches[3];
                          $resto   = trim($matches[4]);

                          $cpf = "";
                          $nome = $resto;

                          // Tenta encontrar algo que se pareça com um CPF (sequência de números com pontos, traços, espaços ou ruídos)
                          // Procuramos por grupos de números que somem cerca de 11 dígitos
                          if (preg_match('/([\d\s.\-+]{11,18})/', $resto, $cpfMatch)) {
                              $cpfCandidate = $cpfMatch[1];

                              // Limpa o CPF para manter apenas o que é importante (números e pontuação básica)
                              $cpf = trim(str_replace(['+', ' '], ['', ''], $cpfCandidate));

                              // Remove o CPF do resto da string para sobrar o nome
                              $nome = trim(str_replace($cpfCandidate, '', $resto));
                          }

                          // Se houver assinatura ou nome repetido após o CPF, tentamos limpar
                          // (Opcional: você pode adicionar lógica para detectar se o nome contém "Assinado")

                          fputcsv($fp, [$dataRaw, $entrada, $saida, $nome, $cpf, 'Detectado']);
                          $linhasEncontradas++;
                      }


                }
            }
        }
        $this->log("Parser: $linhasEncontradas linhas convertidas para CSV.");
        fclose($fp);
    }
}
