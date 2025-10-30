<?php
	namespace App\Model;

	use PDOException;
	use App\Lib\Response;

	class ProdImagenModel {
		private $db;
		private $table = 'prod_imagen';
		private $response;

		public function __CONSTRUCT($db) {
			$this->db = $db;
			$this->response = new Response();
		}

		/***
		 * Función para obtener una imagen de un producto mediante su ID
		 * recibe {id_imagen} ID del registro en la base de datos
		 * regresa: objeto con la información de la imagen
		 */
		public function get($id_imagen) {
			$this->response->result = $this->db
				->from($this->table)
				->where('id_imagen', $id_imagen)
				->fetch();

			if($this->response->result)	$this->response->SetResponse(true);
			else	$this->response->SetResponse(false, 'No existe imagen con ese ID');

			return $this->response;
		}
		/*** Fin de la función */

		/***
		 * Función para obtener todas las imagenes de un producto
		 * recibe {producto_id} ID del producto
		 * regresa: objeto con la información de todas las imagenes de un solo producto
		 */
		public function getByProducto($producto_id) {
			// require_once './core/defines.php';

			$this->response->result = $this->db
				->from($this->table)
				->where('producto_id', $producto_id)
				->fetchAll();
			
			/*foreach($this->response->result as &$imagen) {
				if(!file_exists("assets/image/productos/$imagen->nombre")) {
					$this->del($imagen->id_imagen);
					unset($imagen);
				}
			}*/
			
			$this->response->total = count($this->response->result);
			if($this->response->total > 0)	$this->response->SetResponse(true);
			else {
				// $this->response->result[] = ['url_img_default' => URL_IMG_DEFAULT];
				$this->response->SetResponse(false, 'No existe ninguna imagen de dicho producto');
			}
				
			return $this->response;
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

				if($this->response->result) {
					return $this->response->SetResponse(true);
				} else {
					return $this->response->SetResponse(false, 'no se inserto el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				return $this->response->SetResponse(false, "catch: add model prod_imagen: ".$ex->getMessage());
			}
		}
		/*** Fin de la función add */

		/***
		 * Función para editar un registro de prod_imagen mediante su ID
		 * recibe {data} Información de la imagen actualizada
		 * recibe {id_imagen} ID de la imagen
		 * ***/
		public function edit($data, $id_imagen) {
			try {
				$this->response->result = $this->db
					->update($this->table, $data)
					->where('id_imagen', $id_imagen)
					->execute();
					
				if($this->response->result)	$this->response->SetResponse(true, 'actualizado');
				else { $this->response->SetResponse(false, 'no se edito el registro'); }

			}catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: edit model prod_imagen: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función edit */

		/****
		 * Función para dar de baja el registro de una imagen mediante su ID
		 * recibe {id_imagen} ID del registro a dar de baja
		 */
		public function del($id_imagen) {
			try{
				$this->response->result = $this->db
					->deleteFrom($this->table)
					->where('id_imagen', $id_imagen)
					->execute();
					
				if($this->response->result!=0) {
					$this->response->SetResponse(true, 'id baja: '.$id_imagen);
				} else {
					$this->response->SetResponse(false, 'no se dio de baja el registro');
				}
			} catch(\PDOException $ex) {
				$this->response->errors = $ex;
				$this->response->SetResponse(false, "catch: del model prod_imagen: ".$ex->getMessage());
			}

			return $this->response;
		}
		/*** Fin de la función del */

		public function getThumbnail($prod) {
			$th = '/assets/image/no_imagen.jpg';
			$result = $this->db
				->from($this->table)
				->where('producto_id', $prod)
				->fetch();
			if(is_object($result)) {
				$th = '/assets/image/productos/th_'.$result->nombre;
			}
			
			return $th;
		}

		/***
		 * Función para mover una imagen de un producto al servidor 
		 * recibe {img} archivo con la imagen a subir
		 * recibe {thumb_width} tamaño de la imagen
		 * recibe {newfilename} ruta donde se guardará la imagen
		 * ***/
		public function resize($img, $thumb_width, $newfilename) {
			$max_width=$thumb_width;

			//Check if GD extension is loaded
			if (!extension_loaded('gd') && !extension_loaded('gd2')) {
				trigger_error("GD is not loaded", E_USER_WARNING);
				return false;
			}

			//Get Image size info
			list($width_orig, $height_orig, $image_type) = getimagesize($img);
			switch ($image_type) {
				case 1: $im = imagecreatefromgif($img); break;
				case 2: $im = imagecreatefromjpeg($img);  break;
				case 3: $im = imagecreatefrompng($img); break;
				default:  trigger_error('Unsupported filetype!', E_USER_WARNING);  break;
			}

			/*** calculate the aspect ratio ***/
			$aspect_ratio = (float) $height_orig / $width_orig;

			/*** calulate the thumbnail width based on the height ***/
			$thumb_height = round($thumb_width * $aspect_ratio);

			while($thumb_height>$max_width) {
				$thumb_width-=10;
				$thumb_height = round($thumb_width * $aspect_ratio);
			}

			$newImg = imagecreatetruecolor($thumb_width, $thumb_height);

			/* Check if this image is PNG or GIF, then set if Transparent*/ 
			if(($image_type == 1) OR ($image_type==3)) {
				imagealphablending($newImg, false);
				imagesavealpha($newImg,true);
				$transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
				imagefilledrectangle($newImg, 0, 0, $thumb_width, $thumb_height, $transparent);
			}
			imagecopyresampled($newImg, $im, 0, 0, 0, 0, $thumb_width, $thumb_height, $width_orig, $height_orig);

			//Generate the file, and rename it to $newfilename
			switch ($image_type) {
				case 1: imagegif($newImg, $newfilename); break;
				case 2: imagejpeg($newImg, $newfilename);  break;
				case 3: imagepng($newImg, $newfilename); break;
				default:  trigger_error('Failed resize image!', E_USER_WARNING);  break;
			}

			return $newfilename;
		}

		public function saveImg($file, $id) {
			$directory  = 'assets/image/productos/';
			$extension  = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

			$filename = $id."_".time().".".$extension;
			$file->moveTo($directory.DIRECTORY_SEPARATOR.$filename);

			$this->response->filename = $filename;
			return $this->response->SetResponse(true);
		}
	}
	/*** Fin  de la clase ProdImagenModel */
?>