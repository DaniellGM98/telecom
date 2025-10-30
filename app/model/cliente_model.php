<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ClienteModel {
		private $db;
		private $table = 'cliente'; 
		private $tableU = 'usuario'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->select("id, nombre, apellidos, telefono, email, email AS correo, COALESCE(rfc, '') AS rfc, COALESCE(uso_cfdi, '') AS uso_cfdi, razon_social, usuario_tipo_id, status")
				->leftJoin("$this->tableU on usuario_id = id")
				->where('id', $id)
				->where("status > 0")
				->fetch();

			if($this->response->result) $this->response->SetResponse(true);
			else $this->response->SetResponse(false, 'no existe el registro');

			return $this->response;
		}

		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select("id, nombre, apellidos, telefono, email AS correo, COALESCE(rfc, '') AS rfc, COALESCE(uso_cfdi, '') AS uso_cfdi, razon_social, usuario_tipo_id, status")
				->leftJoin("$this->tableU on usuario_id = id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetchAll();
				
			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select("COUNT(*) AS total")
				->leftJoin("$this->tableU on usuario_id = id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status", 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getAll($pagina=0, $limite=0, $filtro=0) {
			$filtro = $filtro==0? "_": $filtro;
			if($limite != 0) {
				$inicial = $pagina * $limite;
				$clientes = $this->db
					->from($this->table)
					->select("id, nombre, apellidos, telefono, email AS correo, COALESCE(rfc, '') AS rfc, COALESCE(uso_cfdi, '') AS uso_cfdi, razon_social, usuario_tipo_id, status")
					->leftJoin("$this->tableU on usuario_id = id")
					->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
					->where("status > 0")
					->limit("$inicial, $limite")
					->orderBy('apellidos ASC')
					->fetchAll();
			} else {
				$clientes = $this->db
					->from($this->table)
					->select("id, nombre, apellidos, telefono, email AS correo, COALESCE(rfc, '') AS rfc, COALESCE(uso_cfdi, '') AS uso_cfdi, razon_social, usuario_tipo_id, status")
					->leftJoin("$this->tableU on usuario_id = id")
					->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
					->where("status > 0")
					->orderBy('apellidos ASC')
					->fetchAll();
			}
			// foreach($clientes as $cliente) {
			// 	foreach($cliente as $field => $value) {
			// 		// $cliente->$field = utf8_decode($value);
			// 	}
			// }
			$this->response->result = $clientes;

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) Total')
				->leftJoin("$this->tableU on usuario_id = id")
				->where("CONCAT_WS(' ', nombre, apellidos, telefono, email) LIKE '%$filtro%'")
				->where("status > 0")
				->fetch()
				->Total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				// if($this->response->result) $this->response->SetResponse(true, 'id del registro: '.$resultado);
				// else $this->response->SetResponse(false, 'no se inserto el registro');

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model cliente');
			}
				
			return $this->response->SetResponse(true);
		}

		public function edit($data, $usuario_id) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('usuario_id', $usuario_id)
					->execute();

				$this->response->SetResponse(true);

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model cliente');
			}

			return $this->response;
		}

		/*** 
			**** Buscar venta por cliente 
			**** Recibe filtro: nombre, apellido, rfc, razón social
			**** Autor: Angel Gabriel Ramirez Alva
			**** Fecha: 25 Octubre 2019
		***/
		public function buscaVentaByCliente($filtro) {
				$this->response->result = $this->db
					->from($this->table)->select(null)
					->select('SQL_CALC_FOUND_ROWS venta.*')
					->innerJoin('venta ON cliente.id = venta.fk_cliente')
					->where('concat(nombre," ", apellidos," ",rfc," ",razon_social) LIKE ?' , "%$filtro%")
					->fetchAll();
				
				$this->response->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
				return $this->response->SetResponse(true);
		}
	}
?>
