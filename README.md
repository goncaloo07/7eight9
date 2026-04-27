# 7eight9

[Versão em Português abaixo](#português)

---

## English

7eight9 is a virtual clothing store developed as a PAP (Prova de Aptidão Profissional) school project. Products appear in random order by default, so users discover new items instead of being shown only the most popular or expensive ones.

**Live website:** https://alpha.soaresbasto.pt/~a25385/PAP/

---

### Features

**Customers**
- Browse and filter clothing by category, size, colour and price
- Product detail page with photos, sizes and stock availability
- Shopping cart and checkout
- Order history, wishlist and account management
- Saved payment methods (stored encrypted)

**Administrators**
- Admin panel to manage all database tables (products, categories, brands, colours, sizes, carousel, customers, orders)
- Stock management per size
- Carousel image management

---

### Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 |
| Database | MySQL |
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 4 |
| Libraries | jQuery, Font Awesome, Bootstrap Icons, Owl Carousel |

---

### Project Structure

```
PAP/
├── index.php               # Homepage
├── roupa.php               # Product listing with filters
├── roupa2.php              # Product detail page
├── carrinho.php            # Shopping cart
├── compras.php             # Order history
├── wishlist.php            # Wishlist
├── login.php / register.php / logout.php
├── editaccount.php         # Account settings
├── admin.php               # Admin panel
├── stock_admin.php         # Stock management
├── about.php               # About page
├── header.php / footer.php # Shared layout
├── config.php              # DB credentials — not included in repo
├── connection.php          # DB connection — not included in repo
├── sqlconnection.php       # DB connection — not included in repo
├── css/                    # Stylesheets
├── js/                     # JavaScript files
├── img/                    # Static images
└── DB/                     # Database schema diagram
```

---

### Local Setup

1. Clone the repository.

2. Create your config files from the provided examples:
   ```bash
   cp config.example.php config.php
   cp connection.example.php connection.php
   cp sqlconnection.example.php sqlconnection.php
   ```
   Fill in your database credentials.

3. Import the database schema using the `DB/DER_PAP.png` diagram as a reference to create the tables manually.

4. Serve the project with a local PHP server (e.g. XAMPP or WAMP) pointing to the `PAP/` folder.

---

### Author

Goncalo Pinto — PAP 2024/2025
gdbp2007@gmail.com

---
---

<a name="português"></a>

## Português

7eight9 é uma loja de roupa virtual desenvolvida como projeto PAP (Prova de Aptidão Profissional). Os produtos aparecem por defeito em ordem aleatória, para que os utilizadores descubram artigos novos em vez de apenas os mais populares ou caros.

**Website publicado:** https://alpha.soaresbasto.pt/~a25385/PAP/

---

### Funcionalidades

**Clientes**
- Navegar e filtrar roupa por categoria, tamanho, cor e preço
- Página de detalhe com fotos, tamanhos e stock disponível
- Carrinho de compras e checkout
- Histórico de encomendas, lista de desejos e gestão de conta
- Métodos de pagamento guardados (armazenados com encriptação)

**Administradores**
- Painel de administração para gerir todas as tabelas da base de dados (produtos, categorias, marcas, cores, tamanhos, carousel, clientes, compras)
- Gestão de stock por tamanho
- Gestão das imagens do carousel

---

### Tecnologias

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8 |
| Base de Dados | MySQL |
| Frontend | HTML5, CSS3, JavaScript, Bootstrap 4 |
| Bibliotecas | jQuery, Font Awesome, Bootstrap Icons, Owl Carousel |

---

### Estrutura do Projeto

```
PAP/
├── index.php               # Página inicial
├── roupa.php               # Listagem de produtos com filtros
├── roupa2.php              # Página de detalhe do produto
├── carrinho.php            # Carrinho de compras
├── compras.php             # Histórico de compras
├── wishlist.php            # Lista de desejos
├── login.php / register.php / logout.php
├── editaccount.php         # Definições de conta
├── admin.php               # Painel de administração
├── stock_admin.php         # Gestão de stock
├── about.php               # Sobre nós
├── header.php / footer.php # Layout partilhado
├── config.php              # Credenciais BD — não incluído no repo
├── connection.php          # Ligação à BD — não incluído no repo
├── sqlconnection.php       # Ligação à BD — não incluído no repo
├── css/                    # Folhas de estilo
├── js/                     # Ficheiros JavaScript
├── img/                    # Imagens estáticas
└── DB/                     # Diagrama do esquema da base de dados
```

---

### Instalação Local

1. Clonar o repositório.

2. Criar os ficheiros de configuração a partir dos exemplos:
   ```bash
   cp config.example.php config.php
   cp connection.example.php connection.php
   cp sqlconnection.example.php sqlconnection.php
   ```
   Preencher com as credenciais da base de dados.

3. Importar o esquema usando o diagrama `DB/DER_PAP.png` como referência para criar as tabelas manualmente.

4. Servir o projeto com um servidor PHP local (ex. XAMPP ou WAMP) apontando para a pasta `PAP/`.

---

### Autor

Goncalo Pinto — PAP 2024/2025
gdbp2007@gmail.com