<?php 
  session_start();
  if(isset($_SESSION['unique_id'])){
    header("location: users.php");
  }
?>

<?php include_once "header.php"; ?>
<body>
  <div class="wrapper">
    <section class="form signup">
      <header>TypeChat<i class="far fa-comments"></i></i></header>
      <form action="#" method="POST" enctype="multipart/form-data" autocomplete="off">
        <div class="error-text"></div>
        <div class="name-details">
          <div class="field input">
            <label>Primeiro Nome</label>
            <input type="text" name="fname" placeholder="Primeiro nome" required>
          </div>
          <div class="field input">
            <label>Sobrenome</label>
            <input type="text" name="lname" placeholder="Sobrenome" required>
          </div>
        </div>
        <div class="field input">
          <label>Endereço de Email</label>
          <input type="text" name="email" placeholder="Entre com seu email" required>
        </div>
        <div class="field input">
          <label>Senha</label>
          <input type="password" name="password" placeholder="Entre com sua senha" required>
          <i class="fas fa-eye"></i>
        </div>
        <div class="field image">
          <label>Selecionar Imagem de perfil</label>
          <input type="file" name="image" accept="image/x-png,image/gif,image/jpeg,image/jpg" required>
        </div>
        <div class="field button">
          <input type="submit" name="submit" value="Continue para o Chat">
        </div>
      </form>
      <div class="link">Já se inscreveu? <a href="login.php">Login aqui</a></div>
    </section>
  </div>

  <script src="javascript/pass-show-hide.js"></script>
  <script src="javascript/signup.js"></script>

</body>
</html>
