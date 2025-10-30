<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class VentaHistoriaModel {
		private $db;
		private $table = 'venta_historia'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener la información del registro mediante su ID
		 * recibe {id_historia_venta} ID del usuario
		 * regresa: objeto con la información del registro
		 */
		public function get($id_historia_venta) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_historia_venta', $id_historia_venta)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningun registro con ese ID');
			
			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro en la base de datos
		 * recibe {data} Arreglo con la información del registro
		 * ***/
		public function add($data) {
			try {
				$this->response->result = $this->db
					->insertInto($this->table, $data)
					->execute();

				if($this->response->result)	$this->response->SetResponse(true);
				else { $this->response->SetResponse(false, 'no se inserto el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, 'catch: add model venta_historia'.$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función add */

		public function getByVenta($venta) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_venta', $venta)
				->orderBy('fecha')
				->fetchAll();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningun registro con ese ID');
			
			return $this->response;
		}
	}
	/*** Fin  de la clase VentaHistoriaModel */
?>