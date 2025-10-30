<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class TraspasoDetalleModel {
		private $db;
		private $table = 'traspaso_detalle';
		private $tableP = 'producto';
		private $tableC = 'prod_categoria';
		private $tableM = 'marca';
		private $response;
		
		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id', $id)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else { $this->response->SetResponse(false, 'no existe el registro'); }
			return $this->response;
		}

		public function getByTraspaso($traspaso_id, $producto_id=0) {
			$this->response->result = $this->db
				->from($this->table)
				->select("CONCAT(CASE WHEN ($this->tableP.sku IS NOT NULL AND LENGTH($this->tableP.sku)>0) THEN CONCAT('[', $this->tableP.sku, '] ') ELSE '' END, $this->tableP.nombre, ' ', modelo) AS producto, $this->tableC.nombre AS categoria, $this->tableM.nombre AS marca")
				->leftJoin("$this->tableP ON $this->tableP.id = producto_id")
				->leftJoin("$this->tableC ON $this->tableC.id = $this->tableP.prod_categoria_id")
				->leftJoin("$this->tableM ON $this->tableM.id = $this->tableP.marca_id")
				->where('traspaso_id', $traspaso_id)
				->where(intval($producto_id)!=0? "producto_id = $producto_id": 'true')
				->where("$this->table.status", 1)
				->fetchAll();

			$this->response->sumaProductos = $this->db
				->from($this->table)
				->select("SUM(cantidad) AS total")
				->where('traspaso_id', $traspaso_id)
				->where(intval($producto_id)!=0? "producto_id = $producto_id": 'true')
				->where('status', 1)
				->fetch()
				->total;

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) total')
				->where('traspaso_id', $traspaso_id)
				->where(intval($producto_id)!=0? "producto_id = $producto_id": 'true')
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function getByProducto($producto_id) {
			$this->response->result = $this->db
				->from($this->table)
				->where('producto_id', $producto_id)
				->where('status', 1)
				->fetchAll();

			$this->response->total = $this->db
				->from($this->table)
				->select(null)->select('COUNT(*) total')
				->where('producto_id', $producto_id)
				->where('status', 1)
				->fetch()
				->total;

			return $this->response->SetResponse(true);
		}

		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: add model $this->table");
			}

			return $this->response;
		}

		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado: $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model $this->table");
			}

			return $this->response;
		}

		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id baja: $id"); }
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}

		public function delByTraspaso($traspaso_id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('traspaso_id', $traspaso_id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no se dieron de baja los registros'); }
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model $this->table");
			}

			return $this->response;
		}
	}
?>