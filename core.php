<?php

function debug($log = NULL, $type = 'log')
{
    if (defined('DEBUG') && DEBUG && !is_null($log)) {
        if (is_array($log)) {
            $log = "JSON.parse('" . json_encode($log) . "')";
        } else {
            // Remover mudanças de linha
            $log = str_replace(array("\r", "\n"), '', $log);
            $log = "'" . addslashes($log) . "'";
        }
        echo "<script>console.$type($log);</script>";
    }
}

/**
 * Cria uma ligação ao servidor MySQL
 * @param array $db
 * @return ligação MySQL
 */
function connectDB($db)
{
    $ligacao = mysqli_connect($db['host'], $db['user'], $db['pwd'], $db['dbname'], $db['port']);
    mysqli_set_charset($ligacao, $db['charset']);
    if (mysqli_connect_errno()) {
        debug("Failed to connect to MySQL: " . mysqli_connect_error());
        exit();
    }
    return $ligacao;
}
/**
 * Executar SQL na Base de Dados
 * @param \mysqli $ligacao
 * @param SQL $sql
 * @return bool
 */
function queryDB($ligacao, $sql)
{
    // Validar ligação à BD e SQL
    if (is_a($ligacao, 'mysqli') && $sql != '') {
        debug("MySQL> $sql");
        // Executar query na ligação à BD
        $result = mysqli_query($ligacao, $sql);
        // Verificar se ocorreram erros na execução
        if (mysqli_error($ligacao) != '') {
            debug("MySQL: [" . mysqli_errno($ligacao) . "] " . mysqli_error($ligacao), 'error');
            return false;
        }
        if (is_a($result, 'mysqli_result')) {
            $rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
            debug($rows, 'info');
            return $rows;
        } else {
            return $result;
        }
    } else {
        return false;
    }
}