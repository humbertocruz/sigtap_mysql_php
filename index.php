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
require_once('database.php');

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
	return('CREATE TABLE IF NOT EXISTS '.$tabela.' (');
}

// Leitura da linha de informação dos dados
function ler_dados($dados) {
	
}

// Leitura da linha das colunas
function ler_campo($campo,$last = false) {
	global $tabelas, $tabela_nome;
	list($coluna,$tamanho,$inicio,$fim,$tipo) = $campo;
	$ret = strtolower( $coluna ).' '.mysql_types($coluna,$tipo,$tamanho);
	if (!$last) $ret.= ', ';
	return ( $ret );
}

function carrega_dados($tabela_nome) {
	global $tabelas, $sigtap_dir;
	$lay_linha = $tabelas[$tabela_nome]['campos'];
	$arq_dados = file($sigtap_dir.$tabela_nome.'.txt');
	$insert_sql_ar = array();
	$insert_sql = '';
	$count_lin = 1;
	foreach( $arq_dados as $linha ) {
		$insert_sql.= 'INSERT INTO '.$tabela_nome.' VALUES ';
		$insert_sql.= ' ( ';
		$count_col = 1;
		foreach( $lay_linha as $coluna ) {
			$insert_sql.= mysql_col_types($linha, $coluna);
			if ( $count_col < count($lay_linha) ) $insert_sql.=', ';
			$count_col++;
		}
		$insert_sql.= ' ); ';
		array_push( $insert_sql_ar, $insert_sql );
		$insert_sql = '';
		$count_lin++;
		$count_col = 0;
	}
	return($insert_sql_ar);
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
			// Reinicia o tipo de Linha
			$tipo = 0;
	
			// Para a execução e começa a próxima linha
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
		$sql_insert = 'CREATE TABLE IF NOT EXISTS '.$key.' (';
		$num_campos = count($tabelas[$key]['campos']);
		foreach ($tabelas[$key]['campos'] as $camp) {
			$num_campos--;
			if ($num_campos == 0) $last = true; else $last = false;
			$sql_insert.= ler_campo($camp, $last);
		}
		$sql_insert.= ')';
		$sql_insert.= ' TYPE=innodb; ';
		$tabelas[$key]['sql'] = trim($sql_insert);
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
		foreach($_POST['tabela'] as $tabela_proc) {
			$sql_create.= $tabelas[$tabela_proc]['sql'];
			$sql_insert = carrega_dados( $tabela_proc );
			@$link = mysql_connect($database['host'], $database['user'], $database['pass']);
			if ( !$link ) {
				$mysql_msg = 'Não foi possível conectar. '.mysql_error();
			} else {
				@$db = mysql_select_db( $database['dbase'] );

				if (!$db) {
					$mysql_msg = 'Banco de Dados não encontrado.';
				} else {
					@$qc = mysql_query( $sql_create );
					if (!$qc) {
						$mysql_msg = 'Houve um erro na criação da tabela. '.mysql_error();
					} else {
						foreach($sql_insert as $sql_in) {
							@$qi = mysql_query( utf8_decode( $sql_in ) );
							if (!$qi) {
								$mysql_msg = 'Houve ao inserir dados na tabela. '.mysql_error();
								break;
							}
						}
					}
					
				}
			
				mysql_close( $link );
			}
			
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
				<?php //pr($tabelas); ?>
				<div class="alert alert-info">
					<?php echo $status_exec; ?><br>
					<?php echo (isset($mysql_msg))?($mysql_msg):(''); ?>
				</div>
				<?php if($_POST['tabela']) { ?>
					<pre class="pre-scrollable"><?php echo($sql_create).EOL; ?></pre>
					<pre class="pre-scrollable"><?php pr($sql_insert); ?></pre>
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
