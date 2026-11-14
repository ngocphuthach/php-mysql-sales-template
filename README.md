# Hệ thống quản lý bán nước hoa

Ứng dụng PHP và MySQL giữ cấu trúc hiện tại, bổ sung quản lý sản phẩm nước hoa,
nhà cung cấp, nhà vận chuyển, khách hàng, nhân viên, đơn hàng và báo cáo.

## Nâng cấp cơ sở dữ liệu hiện có

Sao lưu database trước khi chạy migration. Migration trong
`database/migrations/20261111_add_perfume_store_features.sql` chỉ chạy một lần
trên database cũ; database mới dùng `database/schema.sql`.

```sh
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" sales_db' < database/migrations/20261111_add_perfume_store_features.sql
```

Trong Windows PowerShell, dùng:

```powershell
Get-Content -Raw .\database\migrations\20261111_add_perfume_store_features.sql |
    docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" sales_db'
```

## Tạo quản trị viên đầu tiên

Sau khi migration hoàn tất, chạy lệnh sau trong terminal tương tác. Mật khẩu sẽ
được nhập tại prompt và không được ghi vào lệnh:

```sh
docker compose exec -it web php /var/www/src/scripts/create-admin.php admin@example.com Admin Nguyen
```

Tài khoản quản trị đầu tiên chỉ được tạo bằng lệnh này khi database chưa có
quản trị viên đang hoạt động. Các tài khoản tiếp theo được tạo trong trang
Quản lý Nhân viên.

Đăng nhập nhân viên/quản trị viên tại `/login.php`; sau khi đăng nhập, trang
quản trị nằm ở `/admin/`. Tài khoản quản trị hiện được khởi tạo cho database
đang chạy; mật khẩu ban đầu được cung cấp riêng và không lưu trong mã nguồn.
Hãy đổi mật khẩu ngay sau lần đăng nhập đầu tiên tại Quản lý Nhân viên.

Đơn hàng được cộng điểm khi chuyển sang trạng thái hoàn thành, theo quy tắc
10.000 đồng giá trị sản phẩm = 1 điểm; phí vận chuyển không tính điểm. Khách
hàng có thể xem điểm và lịch sử mua hàng trong trang tài khoản.