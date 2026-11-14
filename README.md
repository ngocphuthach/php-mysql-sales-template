# Website bán nước hoa Maison Parfum

Đây là đồ án xây dựng website bán nước hoa bằng PHP và MySQL. Website có phần
khách hàng để xem, lọc và đặt mua sản phẩm; phần quản trị dùng để quản lý hoạt
động bán hàng. Giao diện được làm bằng Bootstrap và nội dung tiếng Việt.

## Các chức năng chính

### Dành cho khách hàng

- Xem trang chủ, thương hiệu và danh sách nước hoa.
- Lọc sản phẩm theo thương hiệu, danh mục Nam/Nữ/Unisex và khoảng giá.
- Xem thông tin chi tiết, dung tích, nồng độ, giá và hình ảnh sản phẩm.
- Thêm sản phẩm vào giỏ hàng, chọn đơn vị vận chuyển và đặt hàng.
- Đăng ký, đăng nhập, cập nhật thông tin tài khoản và xem lịch sử mua hàng.
- Tích lũy điểm khi đơn hàng được chuyển sang trạng thái hoàn thành. Mỗi
  10.000 đồng tiền sản phẩm được tính một điểm; phí vận chuyển không tính điểm.

### Dành cho nhân viên và quản trị viên

- Quản lý sản phẩm, hình ảnh, tồn kho, giá và khuyến mãi.
- Quản lý danh mục, nhà cung cấp, nhà vận chuyển, khách hàng và nhân viên.
- Theo dõi đơn hàng, cập nhật trạng thái và xem báo cáo bán hàng.
- Phân quyền các chức năng quản trị theo vai trò nhân viên hoặc quản trị viên.

## Công nghệ sử dụng

- PHP 8.3 và MySQL 8.4.
- HTML, CSS, JavaScript và Bootstrap 5.
- Docker Compose để chạy ứng dụng và cơ sở dữ liệu.

## Chạy dự án trên máy cá nhân

Bạn cần cài Docker Desktop có hỗ trợ Docker Compose. Mở terminal tại thư mục
dự án rồi chạy:

```sh
docker compose up -d --build
```

Sau khi hai container khởi động, mở website tại:

```text
http://localhost:8080
```

Trang đăng nhập nằm ở `/login.php`; khu vực quản trị nằm ở `/admin/`.

Khi MySQL khởi tạo một database mới, các file `database/schema.sql` và
`database/seed.sql` được dùng để tạo bảng và dữ liệu mẫu. Database được lưu
trong Docker volume nên vẫn còn sau khi dừng container. Dùng `docker compose
down` để dừng dự án; **không dùng `docker compose down -v` nếu muốn giữ dữ liệu**.

> Cấu hình trong `compose.yaml` và `src/config/database.php` hiện phục vụ chạy
> local. Hãy đổi thông tin kết nối và mật khẩu trước khi triển khai lên máy chủ.

## Cập nhật database đã có dữ liệu

Nếu đang nâng cấp database cũ, hãy sao lưu dữ liệu trước. Chạy ba migration
sau theo đúng thứ tự; mỗi migration chỉ áp dụng một lần cho database tương ứng:

1. `database/migrations/20261111_add_perfume_store_features.sql`
2. `database/migrations/20261112_add_perfume_categories.sql`
3. `database/migrations/20261114_align_orders_for_online_checkout.sql`

Chạy từng lệnh từ thư mục gốc của dự án:

```sh
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" sales_db < /docker-entrypoint-initdb.d/migrations/20261111_add_perfume_store_features.sql'
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" sales_db < /docker-entrypoint-initdb.d/migrations/20261112_add_perfume_categories.sql'
docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" sales_db < /docker-entrypoint-initdb.d/migrations/20261114_align_orders_for_online_checkout.sql'
```

Không chạy các migration này trên database đã có đủ những thay đổi tương ứng.
Database mới tạo từ `schema.sql` không cần chạy lại các migration trên.

## Tạo quản trị viên đầu tiên

Sau khi database đã sẵn sàng, có thể tạo tài khoản quản trị viên bằng lệnh:

```sh
docker compose exec -it web php /var/www/src/scripts/create-admin.php admin@example.com Admin Nguyen
```

Lệnh sẽ yêu cầu nhập mật khẩu ở terminal; mật khẩu không cần ghi vào câu lệnh.
Tạo quản trị viên đầu tiên bằng cách này chỉ thực hiện được khi database chưa
có quản trị viên đang hoạt động. Sau đó, đăng nhập tại `/login.php`. Nhân viên
và tài khoản quản trị tiếp theo có thể được quản lý trong khu vực quản trị.

## Cấu trúc thư mục

```text
database/   Cấu trúc, dữ liệu mẫu và các migration
docker/     Cấu hình image chạy PHP
public/     Trang khách hàng, trang quản trị và tài nguyên giao diện
src/        Cấu hình và các thành phần dùng chung
```
