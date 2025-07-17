# VM Management PHP

libvirt-phpモジュールを使用してVMの作成・起動を行うミニマムなPHPアプリケーション

## 概要

このアプリケーションは、libvirtとlibvirt-phpモジュールを活用して、同一環境上で仮想マシン（VM）を作成・起動するシンプルなPHPアプリケーションです。基本的なVMライフサイクル管理機能を提供し、OpenVSwitchを使用したVLANネットワーク分離機能も含みます。

## 主な機能

- VM作成・起動の統合機能
- OpenVSwitchを使用したVLANネットワーク管理
- ユーザー毎のVLAN分離（user1: VLAN 100, user2: VLAN 101, user3: VLAN 102）
- SSH接続情報の自動取得・提供
- シンプルなWebインターフェース

## 技術スタック

- PHP 8.1+
- libvirt-php
- Slim Framework
- OpenVSwitch
- PHPUnit (テスト)
- Docker (開発環境)

## 開発環境セットアップ

### 必要な環境

- PHP 8.1以上
- Composer
- Docker & Docker Compose
- libvirt (本番環境)

### インストール

```bash
# リポジトリのクローン
git clone <repository-url>
cd vm-management-php

# 依存関係のインストール
composer install

# 開発環境の起動
docker compose up -d

# pre-commitのインストール（ホストマシン）
pip install pre-commit

# pre-commitフックのインストール
pre-commit install
pre-commit install --hook-type commit-msg
```

## 使用方法

### VM作成

```bash
# WebインターフェースでのVM作成
curl -X POST http://localhost:8080/create-vm \
  -H "Content-Type: application/json" \
  -d '{
    "name": "vm-001",
    "user": "user1",
    "cpu": 2,
    "memory": 2048,
    "disk": 20
  }'
```

### レスポンス例

```json
{
  "success": true,
  "vm": {
    "name": "vm-001",
    "user": "user1",
    "vlan_id": 100,
    "status": "running",
    "ssh": {
      "ip": "192.168.100.10",
      "username": "ubuntu",
      "password": "generated-password"
    }
  }
}
```

## テスト

```bash
# 単体テストの実行
composer test

# コードスタイルチェック
composer cs-check

# 静的解析
composer analyze
```

## 開発ワークフロー

1. 各機能の実装はfeatureブランチで行う
2. プルリクエストによるコードレビュー
3. Conventional Commitsに従ったコミットメッセージ
4. pre-commitフックによる自動チェック
5. GitHub ActionsによるCI/CD

## ライセンス

MIT License
