<?php
use \Hcode\Page;
use \Hcode\Model\Product;
use \Hcode\Model\Category;
use \Hcode\Model\Cart;
use \Hcode\Model\User;
use \Hcode\Model\Address;
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;

$app->get('/', function(){

    $products = Product::listAll();

    $page = new Page();

    $page->setTpl("index", [
        'products' => Product::checklist($products)
    ]);

});

$app->get("/categories/:idcategory", function($idcategory){

    $page = (isset($_GET['page'])) ? (int)$_GET['page'] :1;

    $category = new Category();

    $category->get((int)$idcategory);

    $pagination = $category->getProductsPage($page);

    $pages = [];

    for ($i=1; $i <=$pagination['pages'];$i++)
    {
        array_push($pages,[
            //antes da ? tem o caminho após a ? tem as variáveis
            'link' => '/categories/'.$category->getidcategory().'?page='.$i,
            'page' => $i

        ]);

    }

    $page = new Page();

    $page->setTpl("category", [
        'category' => $category->getValues(),
        'products' =>$pagination["data"]
    ]);

});

$app->get("/products/:desurl", function($desurl){

    $product = new Product();
    $product->getFromUrl($desurl);

    $page = new Page();

    $page->setTpl("product-detail", [
        //variáveis para carrega na template product-detail
       'product' => $product->getValues(),
       'categories' => $product->getCategories()
    ]);

});

$app->get("/cart", function(){


    $cart = Cart::getFromSession();
    $page = new Page();

    $page->setTpl("cart", [
        'cart' =>$cart->getValues(),
        'products' =>$cart->getProducts(),
        'error' =>Cart::getMsgError()
    ]);


});

$app->get("/cart/:idproduct/add", function($idproduct){

        $product = new Product();

        $product->get((int)$idproduct);

        $cart = Cart::getFromSession();

        $cart->addProduct($product);

        header("Location: /cart");
        exit;

});

$app->get("/cart/:idproduct/minus", function($idproduct){

        $product = new Product();

        $product->get((int)$idproduct);

        $cart = Cart::getFromSession();

        $cart->removeProduct($product);

        header("Location: /cart");
        exit;

});
$app->get("/cart/:idproduct/remove", function($idproduct){

        $product = new Product();

        $product->get((int)$idproduct);

        $cart = Cart::getFromSession();

        $cart->removeProduct($product, true);

        header("Location: /cart");
        exit;

});

$app->post("/cart/freight", function(){

        $cart = Cart::getFromSession();
        $cart->setFreight($_POST['zipcode']);

        header("Location: /cart");
        exit;

});

$app->get("/checkout", function(){

    User::verifyLogin(false);

    $address = new Address();

    $cart = Cart::getFromSession();

    if(isset($_GET['zipcode'])){
        $_GET['zipcode'] = $cart->getdeszipcode();
    }


    if(isset($_GET['zipcode'])){

        $address->loadFromCEP($_GET['zipcode']);

        $cart->setdeszipcode($_GET['zipcode']);

        $cart->save();

        $cart->getCalculateTotal();
    }

    if(!$address->getdesaddress()) $address->setdesaddress('');
    if(!$address->getdescomplement()) $address->setdescomplement('');
    if(!$address->getdesdistrict()) $address->setdesdistrict('');
    if(!$address->getdescity()) $address->setdescity('');
    if(!$address->getdesstate()) $address->setdesstate('');
    if(!$address->getdescountry()) $address->setdescountry('');
    if(!$address->getdeszipcode()) $address->setdeszipcode('');

    $page = new Page();
    $page->setTpl("checkout", [

        'cart' => $cart->getValues(),
        'address' =>$address->getValues(),
        'products' => $cart->getProducts(),
        'error' =>Address::getMsgError()
    ]);

});

$app->post("/checkout", function(){

    User::verifyLogin(false);

    if(!isset($_POST['zipcode']) || $_POST['zipcode']===''){

        Address::setMsgError("Digite o CEP.");
        header("Location: /checkout");
        exit;
    }

    if(!isset($_POST['desaddress']) || $_POST['desaddress']===''){

        Address::setMsgError("Digite o Endereço.");
        header("Location: /checkout");
        exit;
    }

    if(!isset($_POST['desdistrict']) || $_POST['desdistrict']===''){

        Address::setMsgError("Digite o Distrito.");
        header("Location: /checkout");
        exit;
    }

    if(!isset($_POST['descity']) || $_POST['descity']===''){

        Address::setMsgError("Digite a Cidade.");
        header("Location: /checkout");
        exit;
    }

    if(!isset($_POST['desstate']) || $_POST['desstate']===''){

        Address::setMsgError("Digite o Estado.");
        header("Location: /checkout");
        exit;
    }

    if(!isset($_POST['descountry']) || $_POST['descountry']===''){

        Address::setMsgError("Digite o País.");
        header("Location: /checkout");
        exit;
    }



    $user = User::getFromSession();

    $address = new Address();

    $_POST['deszipcode'] = $_POST['zipcode'];
    $_POST['idperson'] = $user->getidperson();

    $address->setData($_POST);

    $address->save();

    $cart = Cart::getFromSession();
    $totals = $cart->getCalculateTotal();

    $order = new Order();

    $order->setData([

        'idcart' => $cart->getidcart(),
        'idaddress' => $address->getidaddress(),
        'iduser' => $user->getiduser(),
        'idstatus' => OrderStatus::EM_ABERTO,
        'vltotal' => $totals['vlprice'] + $cart->getvlfreight()
    ]);

    $order->save();

    header("Location: /order".$order->getidorder());
    exit;


});

$app->get("/login", function(){

    $page = new Page();
    $page->setTpl("login", [
        'error' =>User::getError(),
        'errorRegister' =>User::getErrorRegister(),
        'registerValues' => (isset($_SESSION['registerValues'])) ?   $_SESSION['registerValues'] : ['name' => '', 'email' => '', 'phone' =>'']
    ]);
    //Cá está a busca dos valores de um registo; no register values;


});

$app->post("/login", function(){

    try {

    User::login($_POST['login'], $_POST['password']);

    }catch(Exception $e){

    User::setError($e->getMessage());

    }
    header("Location: /checkout");
    exit;

});

$app->get("/logout", function(){

    User::logout();

    header("Location: /login");
    exit;

});

$app->post("/register", function (){
    //Guarda os valores do registo de alguém, caso dê um erro quando se está a preencher o registo, depois os valores anteriormente preenchidos são colocados no formulário
    $_SESSION['registerValues'] = $_POST;
    //Validação, para obrigar a pessoa a escrever estes parâmetros, neste caso o nome
    if( !isset($_POST['name'])|| $_POST['name'] == ''){

        User::setErrorRegister("Preencha o seu nome!");
        header("Location: /login");
        exit;
    }

    //validação para o email
    if( !isset($_POST['email'])|| $_POST['email'] == ''){

        User::setErrorRegister("Preencha o seu email!");
        header("Location: /login");
        exit;
    }

    //validação para a senha
    if( !isset($_POST['password'])|| $_POST['password'] == ''){

        User::setErrorRegister("Preencha a sua senha!");
        header("Location: /login");
        exit;
    }
    //validação se existe um login igual, não deixa

    if(User::checkLoginExist($_POST['email'])=== true){

        User::setErrorRegister("Este endereço de email já existe");
        header("Location: /login");
        exit;
    }

   $user = new User();

   $user->setData([
       'inadmin' => 0,
       'deslogin' => $_POST['email'],
       'desperson' => $_POST['name'],
       'desemail' => $_POST['email'],
       'despassword' => $_POST['password'],
       'nrphone' => $_POST['phone'],

   ]);

   $user->save();

   User::login($_POST['email'], $_POST['password']);

   header("Location: /checkout");
   exit;

});


$app->get("/forgot", function()
{
    $page = new Page();

    $page->setTpl("forgot");

});

$app->post("/forgot", function(){

    $_POST["email"];

    $user = User::getForgot($_POST["email"], false);

    header("Location: /forgot/sent");
    exit;

});

$app->get("/forgot/sent", function(){

    $page = new Page();

    $page->setTpl("forgot-sent");

});

$app->get("/forgot/reset", function(){

    $user = User::validForgotDecrypt($_GET["code"]);


    $page = new Page();

    $page->setTpl("forgot-reset", array(
        "name" => $user["desperson"],
        "code" => $_GET["code"]
    ));

});$app->post("/forgot/reset", function(){


    $forgot = User::validForgotDecrypt($_POST["code"]);
    User::setForgotUsed($forgot["idrecovery"]);

    $user = new User();

    $user->get((int)$forgot["iduser"]);

    //Creates a password hash
    $password =  password_hash($_POST["password"], PASSWORD_DEFAULT, ["cost" => 12]);

    //atributo no name no input de HTML = password
    $user->setPassword($password);

    $page = new Page();

    $page->setTpl("forgot-reset-success");

});

$app->get("/profile", function(){

    User::verifyLogin(false);

    $user = User::getFromSession();

    $page = new Page();

    $page->setTpl("profile", [

        'user' => $user ->getValues(),
        'profileMsg' => User::getSuccess(),
        'profileError' =>User::getError()
    ]);

});

$app->post("/profile", function(){

    USer::verifyLogin(false);

    //verificação para o preenchimento do formulário

    if(!isset($_POST['desperson']) || $_POST['desperson'] === ''){

        User::setError("Preencha o seu nome");
        header("Location : /profile");
        exit;
    }

    if(!isset($_POST['desemail']) || $_POST['desemail'] === ''){

        User::setError("Preencha o seu email");
        header("Location : /profile");
        exit;
    }

    $user = User::getFromSession();

    if($_POST['desemail'] != $user->getdesemail()){

       if(User::checkLoginExist($_POST['desemail']) === true){

           User::setError("Este endereço de e-mail já está cadastrado");
           header("Location : /profile");
           exit;
       }
    }

    $_POST['inadmin'] = $user->getinadmin();
    $_POST['despassword'] = $user->getdespassword();
    $_POST ['deslogin'] = $_POST['desemail'];

    $user = User::getFromSession();
    $user->setData($_POST);

    $user->save();

    User::setSuccess("Dados alterados com sucesso!");

    header ('Location : /profile');
    exit;


});

$app->get("/order/:idorder", function ($idorder){

    User::verifyLogin(false);

    $order = new Order();

    $order->get((int)$idorder);

   $page = new Page();

   $page->setTpl("payment", [

       'order' => $order->getValues()
   ]);

});

$app->get("/boleto/:idorder", function($idorder){



});