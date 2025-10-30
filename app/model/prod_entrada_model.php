<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdEntradaModel {
		private $db;
		private $table = 'prod_entrada'; 
		private $tableP = 'producto'; 
		private $tablePED = 'prod_entrada_detalle'; 
		private $tableSuc = 'sucursal'; 
		private $response;
		
		public function __CONSTRUCT($db) {
			require_once './core/defines.php';
			$this->db = $db;
			$this->response = new Response();
		}

		public function get($id) {
				$this->response->result = $this->db
					->from($this->table)
					->where('id', $id)
					->fetch();

				if($this->response->result) { $this->response->SetResponse(true); }
				else { $this->response->SetResponse(false, 'no existe el registro'); }

				return $this->response;
		}

		public function find($filtro) {
			$this->response->result = $this->db
				->from($this->table)
				->select(NULL)->select('proveedor_id, empleado_id, fecha, folio, subtotal, total')
				->where("CONCAT_WS(' ', proveedor_id, empleado_id, fecha, folio, subtotal, total) LIKE '%$filtro%'")
				->fetchAll();

			return $this->response->SetResponse(true);
		}

		public function buscaFolio($folio) {
			$this->response->result = $this->db
				->from($this->table)
				->select(null)->select('SQL_CALC_FOUND_ROWS prod_entrada.*')
				->where('folio', $folio)
				->where('status', 1)
				->fetchAll();
			$this->resposne->total = $this->db->getPdo()->query('SELECT FOUND_ROWS()')->fetchColumn();
			
			return $this->response->SetResponse(true);
		}

		// public function getsku($sku) {
		// 	try {
		// 		$this->response->result = $this->db
		// 		->from($this->tablePED)
		// 		//->select(null)->select('sku')
		// 		//  ->select('sku')
		// 		->where('sku' , $sku)
		// 		->where('status', 1)
		// 		->limit('1')
		// 		->fetchAll();
		// 		// ->getQuery();
				
		// 		if($this->response->result) { $this->response->SetResponse(true); }
		// 		else { $this->response->SetResponse(false, 'no existe el registro'); }
		// 		// $this->response->result = $sku;

		// 	} catch(\PDOException $ex) {
		// 		$this->response->errors = $ex;
		// 		$this->response->SetResponse(false, 'catch: add model prod_entrada');

		// 	}
		// 	return $this->response->result;
		// }

		public function getsku($sku) {
			try {
				$respuesta = $this->db
					->from($this->tablePED)
					->where('sku' , $sku)
					->where('status', 1)
					->orderBy('id DESC')
					->limit('1')
					->fetch();

				if($respuesta != null) {

					$idEntrada = $respuesta->prod_entrada_id;
					$idProducto = $respuesta->producto_id;

					$respuesta2 = $this->db
						->from($this->tableP)
						->where('id', $idProducto)
						->where('status', 1)
						->fetch();
						
					if($respuesta2 != null) {
							
						$respuesta3 = $this->db
						->from($this->table)
						->where('id', $idEntrada)
						->where('status', 1)
						->fetch();
						$idSucursal = $respuesta3->sucursal_id;

						$respuesta4 = $this->db
						->from($this->tableSuc)
						->where('id', $idSucursal)
						->where('status', 1)
						->fetch();

						if($respuesta4 != null) {
						}else { 
							$this->response->SetResponse(false, 'no existe el registro'); 
							$this->response->result = array();
							return $this->response->result;
						}

					}else { 
						$this->response->SetResponse(false, 'no existe el registro'); 
						$this->response->result = array();
						return $this->response->result;
					}
					
				}

				$this->response->result = $this->db
				->from($this->tablePED)
				->where('sku' , $sku)
				->where('status', 1)
				->limit('1')
				->fetchAll();
				
				if($this->response->result) { 
					$this->response->SetResponse(true); 
				}else { 
					$this->response->SetResponse(false, 'no existe el registro'); 
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model prod_entrada');

			}
			return $this->response->result;
		}

		/*
		 * getAll
		 * RECIBE: arreglo de id de entradas solo los numeros
		 * $data
		 * REGRESA: Todos los campos de las entradas ingresadas
		 */
		public function getAll($data) {  
					$this->response->result = $this->db
						->from($this->table)
						->where('id', $data)
						->where('status', 1)
						->fetchAll();
			
					return $this->response->SetResponse(true);
		}// fin de getAll 

		/*** Método add inserta una entrada de producto en la base ***/
		public function add($data) {
			try{
				if(!isset($data['fecha'])) { $data['fecha'] = date('Y-m-d H:i:s'); }
				//date_default_timezone_set('America/Mexico_City');
				//$data['fecha'] = date("Y-m-d H:i:s");
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, 'id del registro: '.$this->response->result); }
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model prod_entrada');
			}

			return $this->response;
		}

		/*** edit ***/
		public function edit($data, $id) {
			try{
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0) { $this->response->SetResponse(true, "id actualizado $id"); }
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: edit model prod_entrada');
			}

			return $this->response;
		}//fin de edit


		/*** del ***/
		public function del($id) {
			try{
				$data['status'] = 0;
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id', $id)
					->execute();

				if($this->response->result!=0)	$this->response->SetResponse(true, 'id baja: '.$id);
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: del model prod_entrada');
			}

			return $this->response;
		}
	}//fin de prod_entrada
?>