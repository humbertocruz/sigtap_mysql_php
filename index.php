<?php
session_start();

// Apaga todas as sessões

if (isset($_GET['del'])) {
	unset($_SESSION['processadas']);
	unset($_SESSION['tabelas']);
	unset($_SESSION);
	exit();
}


// Arquivos requeridos
require_once('config.php');
require_once('functions.php');

// Variaveis de Controle
$num = 1; // Contador de Linha
$tipo = 0; // Tipo de linha - 0 = tabela, 1 = dados, 2 = campos
$tabela_sql = ''; // Texto SQL para criar a tabela

// Array com as tabelas
$tabelas = array(); 
$estrutura = array(
	'sql'=>array(),
	'campos'=>array(),
	'dados' => array()
);

$tabela_nome = ''; // Nome da tabela em processamento

// Carrega arquivo de Layout
$layout = file($sigtap_dir.'layout.txt');

// Leitura da linha do nome da tabela
function ler_tabela($tabela = '') {
	return(chr(10).chr(9).'CREATE TABLE IF NOT EXISTS '.$tabela.' ('.chr(10));
}

// Leitura da linha de informação dos dados
function ler_dados($dados) {
	//echo 'Dados: '.$dados.EOL;
}

// Leitura da linha das colunas
function ler_campo($campo,$last = false) {
	global $tabelas, $tabela_nome;
	list($coluna,$tamanho,$inicio,$fim,$tipo) = $campo;
	$ret = chr(9).strtolower( $coluna ).' '.mysql_types($coluna,$tipo,$tamanho);
	if (!$last) $ret.= ', ';
	$ret.= chr(10);
	return ( $ret );
}

function carrega_dados($tabela_nome) {
	global $tabelas, $sigtap_dir;
	$lay_linha = $tabelas[$tabela_nome]['campos'];
	$arq_dados = file($sigtap_dir.$tabela_nome.'.txt');
	$insert_sql = chr(9).'INSERT INTO '.$tabela_nome.' VALUES'.chr(10);
	$count_lin = 1;
	$count_col = 1;
	foreach( $arq_dados as $linha ) {
		$insert_sql.= chr(9).'('.chr(10);
		foreach( $lay_linha as $coluna ) {
			$insert_sql.= chr(9).mysql_col_types($linha, $coluna);
			if ( $count_col < count($lay_linha) ) $insert_sql.=', ';
			$insert_sql.= chr(10);
			$count_col++;
		}
		$insert_sql.= chr(9).')';
		if ( $count_lin < count($arq_dados) ) $insert_sql.=',';
		$insert_sql.= chr(10);
		$count_lin++;
		$count_col = 0;
	}
	return($insert_sql);
}

// Inicia processamento

// Processa linhas do Layout
$tabelas = $_SESSION['tabelas'];

if ( !is_array( $tabelas ) ) {
	// Lendo Arquivo de Layout
	$status_exec = 'Lendo Arquivo: '.count($layout).' linhas';
	foreach( $layout as $linha ) {
		// Apaga espacos extras
		$linha = trim( $linha );
		
		// Se a linha estiver em branco ( Próxima tabela )
		if ($linha == '') {
			// Adiciona ultimo campo
			//$tabela_sql.= chr(9).')'.chr(10);
			// Adiciona tipo de tabela
			//$tabela_sql.= chr(9).' TYPE=innodb;'.chr(10);
	
			//$tabelas[$tabela_nome]['sql'] = $tabela_sql;
			
			// Carrega arquivo de dados
			// $tabelas[$tabela_nome]['dados'] = carrega_dados($sigtap_dir.$tabela_nome.'.txt');
			
			// Reinicia Texto SQL
			//$tabela_sql = '';
			// Reinicia o tipo de Linha
			$tipo = 0;
			
			// Para a execução e começa a próxima linha
			// break;
			continue;
		}
	
	// Controle do tipo de linha lida
		switch($tipo) {
			case 0:
				//$tabela_sql.= ler_tabela($linha);
				$tabela_nome = $linha;
				// Adiciona SQL ao array de tabelas
				$tabelas[$tabela_nome] = $estrutura;
				break;
			case 1:
				//$tabela_sql.= ler_dados($linha); // Nao fazer nada
				break;
			case 2:
				$campos = explode(',', $linha);
				array_push( $tabelas[$tabela_nome]['campos'], $campos );
			break;
		}
		// Controle do tipo de linha
		if ($tipo == 1) $tipo = 2; // A ordem inversa é proposital, para não executar em sequencia
		if ($tipo == 0) $tipo = 1;
		
		// Proxima Linha
		$num++;
	}

	foreach($tabelas as $key=>$value) {
		$sql_insert = chr(10).chr(9).'CREATE TABLE IF NOT EXISTS '.$key.' ('.chr(10);
		$num_campos = count($tabelas[$key]['campos']);
		foreach ($tabelas[$key]['campos'] as $camp) {
			$num_campos--;
			if ($num_campos == 0) $last = true; else $last = false;
			$sql_insert.= ler_campo($camp, $last);
		}
		$sql_insert.= chr(9).')'.chr(10);
		$sql_insert.= chr(9).' TYPE=innodb;'.chr(10);
		$tabelas[$key]['sql'] = $sql_insert;
	}
	$_SESSION['tabelas'] = $tabelas;
	$processadas = array();
	$_SESSION['processadas'] = $processadas;

} else {
	$processadas = $_SESSION['processadas'];
	if (!is_array($processadas)) $processadas = array();
	$status_exec = 'Lendo dados da sessão';
	if($_POST['tabela']) {
		$processadas = array_merge($processadas, $_POST['tabela']);
		$_SESSION['processadas'] = $processadas;
		$sql_create = '';
		$sql_insert = '';
		foreach($_POST['tabela'] as $tabela_proc) {
			$sql_create.= $tabelas[$tabela_proc]['sql'].EOL;
			$sql_insert.= carrega_dados($tabela_proc).EOL;
		}
	}
}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Tabelas Unificadas</title>
		<link rel="stylesheet" href="/bootstrap/css/bootstrap.min.css">
		<style>
			body {
				margin: 4px;
			}
		</style>
	</head>
	<body>
			<div class="container">
				<div class="alert alert-info">
					<?php echo $status_exec; ?>
				</div>
				<?php if($_POST['tabela']) { ?>
					<pre class="pre-scrollable"><?php echo($sql_create); ?></pre>
					<pre class="pre-scrollable"><?php echo($sql_insert); ?></pre>
				<?php } ?>
				<br>
				<form method="post">
				<table class="table table-bordered">
					<tr class="alert alert-info">
						<th class="span1"><input type="checkbox" name="todas"></th>
						<th>Tabela</td>
					</tr>
					<?php
					foreach ( $tabelas as $key=>$value ) { 
						if (in_array($key, $processadas)) $proc = 'class="alert alert-success"';
						else $proc = '';
						?>
						<tr <?php echo $proc; ?>>
							<td><input type="checkbox" name="tabela[]" value="<?php echo $key;?>"></td>
							<td><?php echo $key;?></td>
						</tr>
					<?php } ?>
				</table>
				<div class="form-actions">
					<input type="submit" class="btn btn-primary" value="Processar Tabelas">
				</div>
				</form>
			</div>
	</body>
</html>
