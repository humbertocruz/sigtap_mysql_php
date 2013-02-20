<?php

// Funções de Apoio
function pr($mixed) {
	echo '<pre>';
	print_r($mixed);
	echo '</pre>';
}

// Tipos de colunas e conversão para mySQL
function mysql_types( $name,$type,$size ) {
	switch( $type ) {
		case 'VARCHAR2':
			return ( 'VARCHAR('.$size.')' );
			break;
		case 'NUMBER':
			return( 'INT' );
			break;
		case 'CHAR':
			if (substr($name,0,3) == 'DT_')
				return( 'DATE' );
			else
				return ( 'VARCHAR('.$size.')' );
			break;
		default:
			return ( '' );
			break;
	}
}
function mysql_col_types( $text, $coluna = array() ) {
	list($name,$size,$ini,$end,$type) = $coluna;
	$ini--;
	switch ( $type ) {
		case 'VARCHAR2':
			return ( utf8_encode( '"'.trim( substr( $text, $ini, $size ) ).'"' ) );
			break;
		case 'NUMBER':
			return ( utf8_encode( substr( $text, $ini, $size ) ) );
			break;
		case 'CHAR':
			if (substr($name,0,3) == 'DT_')
				$ret = utf8_encode( '"'.substr( $text, $ini, $size-2 ).'-'.substr( $text, $ini+4, $size-4 ).'-01"' );
			else
				$ret = utf8_encode( '"'.trim( substr( $text, $ini, $size ) ).'"' );
			return ( $ret );
			break;
		default:
			return ( utf8_encode( '"'.trim( substr( $text, $ini, $size ) ).'"' ) );
			break;
	}
}


