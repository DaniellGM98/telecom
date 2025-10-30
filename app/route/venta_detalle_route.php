<?php
	use App\Lib\Response;


 
	/*** Grupo bajo la ruta venta_detalle ***/  
	$app->group('/venta_detalle/', function () {
	    $this->get('', function ($req, $res, $args) {
	        return $res->withHeader('Content-type', 'text/html')
	                   ->write('Soy ruta de venta_detalle');
	    });
	    
		/*** Ruta para obtener los datos de venta_detalle por medio del ID ***/
	    $this->get('get/{id}', function ($req, $res, $args) {
	        return $res->withHeader('Content-type', 'application/json')
					   ->write(json_encode($this->model->venta_detalle->get($args['id'])));
					   
	    });
	    /*** Ruta para buscar venta_detalle ***/
		$this->get('find/{f}', function ($req, $res, $args) {   
			return $res->withHeader('Content-type', 'application/json')
						->write(json_encode($this->model->venta_detalle->find($args['f'])));
			
		});

		$this->get('findBySku/{imei}', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();
			return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->venta_detalle->findBySku($args['imei'])));
	    });

	    /*** Ruta para obtener los datos de los venta_detalle ***/
	    $this->get('getAll/', function ($req, $res, $args) {
	                
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->venta_detalle->getAll()));
	    });

	    /*** Ruta para getByVenta ***/
		$this->get('getByVenta/{id}', function ($req, $res, $args) {   
			return $res->withHeader('Content-type', 'application/json')
						->write(json_encode($this->model->venta_detalle->getByVenta($args['id'])));
			
		});

	    /*** Ruta para agregar un venta_detalle ***/
	    $this->post('add/', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->venta_detalle->add($parsedBody)));
	    });


	    /*** Ruta para modificar un venta_detalle ***/
	    $this->put('edit/{id}', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->venta_detalle->edit($parsedBody,$args['id'])));
	    });

	   	/*** Ruta para dar de baja un venta_detalle ***/
	    $this->put('del/{id}', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->venta_detalle->del($args['id'])));
	    });

	});