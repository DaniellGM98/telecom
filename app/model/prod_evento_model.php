<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdEventoModel {
		private $db;
		private $table = 'tienda_evento'; 
		private $tableProd = 'producto';
		private $tableE = 'prod_entrada';
		private $tableP = 'prod_precio';
		private $tableEv = 'tienda_prod_evento';
		private $tableS = 'prod_stock';
		private $tableV = 'venta';
		private $tableD = 'det_venta';
		private $tableR = 'tienda_review';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id_prod_evento) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_prod_evento', $id_prod_evento)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else $this->response->SetResponse(false);

			return $this->response;
		}

		public function getByEvento($fk_evento, $fk_producto) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_evento', $fk_evento)
				->where('fk_producto', $fk_producto)
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result) {
					return $this->response->SetResponse(true);
				} else {
					return $this->response->SetResponse(false, 'no se inserto el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model prod_evento: ".$ex->getMessage());
			}
		}

		public function edit($data, $id_prod_evento) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_prod_evento', $id_prod_evento)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'actualizado');
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model prod_evento: ".$ex->getMessage());
			}

			return $this->response;
		}

		public function del($id_prod_evento) {
			try{
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_prod_evento', $id_prod_evento)
					->execute();
					
				if($this->response->result!=0)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model prod_evento: ".$ex->getMessage());
			}

			return $this->response;
		}

		public function delByProducto($fk_evento, $fk_producto) {
			try{
				$data = ['status' => 0];
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('fk_evento', $fk_evento)
					->where('fk_producto', $fk_producto)
					->execute();
					
				if($this->response->result!=0)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model prod_evento: ".$ex->getMessage());
			}

			return $this->response;
		}
	}
?>