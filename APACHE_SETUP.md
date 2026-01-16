# Apache/XAMPP Setup for Symfony Backend

## Step 1: Update .env file for MySQL

The `.env` file should use MySQL (since you have XAMPP MySQL running):

```
DATABASE_URL="mysql://root@127.0.0.1:3306/twins_elegance?serverVersion=8.0&charset=utf8mb4"
```

## Step 2: Add Virtual Host to Apache

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` in a text editor (as Administrator)

2. Add this at the end of the file:

```apache
# Twins Elegance API
<VirtualHost *:80>
    ServerName twins-elegance-api.local
    DocumentRoot "C:/Users/Eli20/Desktop/mon-projet-react-final/backend/public"
    
    <Directory "C:/Users/Eli20/Desktop/mon-projet-react-final/backend/public">
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>
    
    # Increase PHP limits
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    
    ErrorLog "C:/xampp/apache/logs/twins-elegance-error.log"
    CustomLog "C:/xampp/apache/logs/twins-elegance-access.log" common
</VirtualHost>
```

3. Open `C:\Windows\System32\drivers\etc\hosts` in Notepad (as Administrator)

4. Add this line:
```
127.0.0.1    twins-elegance-api.local
```

## Step 3: Enable Required Apache Modules

1. Open `C:\xampp\apache\conf\httpd.conf`

2. Make sure these lines are uncommented (remove the # if present):
```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
```

## Step 4: Restart Apache

1. Open XAMPP Control Panel
2. Stop Apache
3. Start Apache

## Step 5: Test

Visit: `http://twins-elegance-api.local/api/products`

Or if you prefer to use localhost directly, you can change the DocumentRoot in httpd.conf or use:
`http://localhost/backend/public/api/products`

## Alternative: Use localhost directly

If you don't want to use a virtual host, you can:

1. Change XAMPP's DocumentRoot in `httpd.conf` to point to your project:
```apache
DocumentRoot "C:/Users/Eli20/Desktop/mon-projet-react-final"
<Directory "C:/Users/Eli20/Desktop/mon-projet-react-final">
    AllowOverride All
    Require all granted
</Directory>
```

2. Then access: `http://localhost/backend/public/api/products`

## Update Frontend API URL

In your frontend `.env` file, set:
```
VITE_API_BASE_URL=http://twins-elegance-api.local
```
or
```
VITE_API_BASE_URL=http://localhost/backend/public
```

