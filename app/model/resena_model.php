<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ResenaModel {
		private $db;
		private $table = 'resena'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener el review mediante su ID
		 * recibe {id_resena} ID del review
		 * regresa: objeto con la información del review con el ID proporcionado
		 */
		public function get($id_resena) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_resena', $id_resena)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningún review con ese ID');

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todos los reviews hechos a un mismo producto
		 * recibe {fk_producto} ID del producto
		 * recibe {fk_cliente} ID del cliente
		 * regresa: objeto con la información de todos los reviews hechos a dicho producto
		 */
		public function getByProducto($fk_producto, $fk_cliente=0) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_producto', $fk_producto)
				->where("fk_cliente ".($fk_cliente==0? '>': '=')." $fk_cliente")
				->fetchAll();

			$this->response->promedio = $this->db
				->from($this->table)
				->select(null)->select('coalesce(avg(puntuacion), 0) as promedio')
				->where('fk_producto', $fk_producto)
				->fetch()
				->promedio;

			$this->response->reviews = $this->db
				->from($this->table)
				->select(null)->select('count(*) as reviews')
				->where('fk_producto', $fk_producto)
				->fetch()
				->reviews;
				
			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todos los reviews hechos por un cliente
		 * recibe {fk_cliente} ID del cliente
		 * regresa: objeto con la información de todos los reviews hechos por el cliente proporcionado
		 */
		public function getByCliente($fk_cliente) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_cliente', $fk_cliente)
				->fetchAll();
				
			return $this->response->SetResponse(true);
		}
		/*** Fin de la función */

		/***
		 * Función para agregar un nuevo registro a la base de datos
		 * recibe {data} Arreglo con la información del nuevo registro
		 * regresa ID del nuevo registro
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
				$this->response->SetResponse(false, "catch: add model review: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un review mediante su ID
		 * recibe {data} Información del review actualizada
		 * recibe {id_resena} ID del review a modificar
		 * ***/
		public function edit($data, $id_resena) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_resena', $id_resena)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'actualizado');
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model review: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja un registro de la base de datos
		 * recibe {id_resena} ID del registro
		 */
		public function del($id_resena) {
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_resena', $id_resena)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model review: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */
	}
	/*** Fin  de la clase ReviewModel */
?>