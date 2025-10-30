<?php
	use App\Lib\Response;


 
	/*** Grupo bajo la ruta usuario_log ***/  
	$app->group('/usuario_log/', function () {
	    $this->get('', function ($req, $res, $args) {
	        return $res->withHeader('Content-type', 'text/html')
	                   ->write('Soy ruta de usuario_log');
	    });
	    
		/*** Ruta para obtener los datos de usuario_log por medio del ID ***/
	    $this->get('get/{id}', function ($req, $res, $args) {
	        return $res->withHeader('Content-type', 'application/json')
					   ->write(json_encode($this->model->usuario_log->get($args['id'])));
					   
	    });
	    /*** Ruta para buscar usuario_log ***/
		$this->get('find/{f}', function ($req, $res, $args) {   
			return $res->withHeader('Content-type', 'application/json')
						->write(json_encode($this->model->usuario_log->find($args['f'])));
			
		});

	    /*** Ruta para obtener los datos de los usuario_log ***/
	    $this->get('getAll/', function ($req, $res, $args) {
	                
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->usuario_log->getAll()));
	    });

	    /*** Ruta para agregar un usuario_log ***/
	    $this->post('add/', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->usuario_log->add($parsedBody)));
	    });


	    /*** Ruta para modificar un usuario_log ***/
	    $this->put('edit/{id}', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->usuario_log->edit($parsedBody,$args['id'])));
	    });

	   	/*** Ruta para dar de baja un usuario_log ***/
	    $this->put('del/{id}', function ($req, $res, $args) {
	        $parsedBody = $req->getParsedBody();        
	        return $res->withHeader('Content-type', 'application/json')
	                   ->write(json_encode($this->model->usuario_log->del($args['id'])));
	    });

	});