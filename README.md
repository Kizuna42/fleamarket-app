# Coachtechフリマ

COACHTECH（プログラミングスクール）の模擬課題として実装したフリマアプリです。実在の企業が開発したものではなく、学習目的でLaravelによるフルスタックのWebアプリケーション開発を一通り経験するために作成しました。

## 機能一覧

- 会員登録・ログイン・ログアウト（Laravel Fortify）
- 商品一覧表示（おすすめ／マイリスト切り替え、キーワード検索、カテゴリ絞り込み、ページネーション）
- 商品詳細表示、いいね機能、コメント投稿
- 商品出品（画像・カテゴリ・ブランド名・商品説明・価格などの登録）
- 商品購入（支払い方法選択：クレジットカード／コンビニ払い、配送先住所の変更）
- マイページ（プロフィール編集、購入した商品一覧、出品した商品一覧）

## 使用技術（実行環境）

- PHP: 7.4.9
- Laravel: 8.75
- Nginx: 1.21.1
- MySQL: 8.0.26
- phpMyAdmin: 5.2.1
- Laravel Mix（フロントエンドビルド）
- Docker / Docker Compose

> **注意（EOLについて）**: 本アプリが使用しているPHP 7.4およびLaravel 8は、いずれも公式サポート（EOL）が終了しています。本リポジトリは学習用の模擬課題成果物であり、これらのバージョンは学習当時の課題要件に合わせたものです。実運用に転用する場合はバージョンアップを推奨します。

## ER図

![ER図](index.drawio.png)

## アプリケーションURL

- 開発環境：http://localhost:8081/
- phpMyAdmin：http://localhost:8082/

## 環境構築

1. リポジトリをクローン
```bash
git clone <リポジトリのURL>
cd <リポジトリ名>
```
2. Dockerコンテナのビルド
```bash
docker compose up -d --build
```
3. コンテナに入る
```bash
docker compose exec php bash
```
4. パッケージをインストール
```bash
composer install
```
5. 環境ファイルを設定
```bash
cp .env.example .env
php artisan key:generate
```
※`.env`のDB接続情報を必要に応じて以下のように変更してください
```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=laravel_pass
```
6. シンボリックリンクの作成
```bash
php artisan storage:link
```
7. データベースのマイグレーションとシーディング
```bash
php artisan migrate --seed
```

※実際の動作確認は会員登録からお願いいたします。

## テスト実行方法

phpコンテナ上で実行してください
```bash
php artisan test
```

Feature配下に主要機能を検証するテストを11本用意しています（直下8本：商品一覧・出品・いいね・コメント・購入・支払い方法・配送先住所・プロフィール、Auth配下3本：ログイン・ログアウト・会員登録）。

## 主要ページ

- 商品一覧画面（トップ画面）: /
- 商品一覧画面（トップ画面）_マイリスト: /?tab=mylist
- 会員登録画面: /register
- ログイン画面: /login
- 商品詳細画面: /item/:item_id
- 商品購入画面: /purchase/:item_id
- 住所変更ページ: /purchase/address/:item_id
- 商品出品画面: /sell
- プロフィール画面: /mypage
- プロフィール編集画面: /mypage/profile
- プロフィール画面_購入した商品一覧: /mypage?tab=buy
- プロフィール画面_出品した商品一覧: /mypage?tab=sell
