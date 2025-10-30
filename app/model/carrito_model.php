<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class CarritoModel {
		private $db;
		private $table = 'carrito'; 
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener el carrito mediante su ID
		 * recibe {id_carrito} ID del carrito
		 * regresa: objeto con la información del carrito con el ID proporcionado
		 */
		public function get($id_carrito) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_carrito', $id_carrito)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningún item con ese ID');

			return $this->response;
		}
		/*** Fin de la función */


		public function getByProd($prod, $cli) {
			$this->response->result = $this->db
				->from($this->table)
				->where('fk_producto', $prod)
				->where('fk_cliente', $cli)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe ningún item con ese ID');

			return $this->response;
		}

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
				$this->response->SetResponse(false, "catch: add model carrito: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un carrito mediante su ID
		 * recibe {data} Información del carrito actualizada
		 * recibe {id_carrito} ID del carrito a modificar
		 * ***/
		public function edit($data, $id_carrito) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_carrito', $id_carrito)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'actualizado');
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model carrito: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja un registro de la base de datos
		 * recibe {id_carrito} ID del registro
		 */
		public function del($id_carrito) {
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_carrito', $id_carrito)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model carrito: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */

		public function delByCliProd($cli, $prod) {
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('fk_cliente', $cli)
					->where('fk_producto', $prod)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'registro dado de baja');
				else { $this->response->SetResponse(false, 'no se dio de baja el registro'); }

			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model carrito: ".$ex->getMessage());
			}

			return $this->response;
		}
	}
	/*** Fin  de la clase carritoModel */
?>