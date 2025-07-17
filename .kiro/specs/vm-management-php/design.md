# 設計書

## 概要

libvirt-phpモジュールを使用してVMの作成・起動を同時に行うミニマムなPHPアプリケーションを設計します。アプリケーションは単一のエンドポイントでVM作成から起動、SSH接続情報の提供までを一貫して処理し、シンプルで使いやすいインターフェースを提供します。

## アーキテクチャ

### システム構成

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Web Interface │────│  PHP Application │────│   libvirt API   │
│   (Simple Form)  │    │   (Core Logic)   │    │  (VM Management) │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                │
                        ┌─────────────────┐
                        │   File System   │
                        │ (VM Images/Logs) │
                        └─────────────────┘
```

### レイヤー構造

1. **プレゼンテーション層**: シンプルなHTMLフォームとレスポンス表示
2. **ビジネスロジック層**: VM作成・起動・管理ロジック
3. **データアクセス層**: libvirt-php APIとの連携
4. **インフラ層**: ファイルシステム、ログ管理

## コンポーネントと インターフェース

### 主要コンポーネント

#### 1. VMManager クラス（メインクラス）
- **責務**: VM作成・起動・管理のすべての機能を統合
- **メソッド**:
  - `createAndStartVM($params)`: VM作成と起動を同時実行し、SSH情報を含む結果を返す

#### 2. SimpleVM クラス（データ構造）
- **責務**: VM情報とSSH情報を保持する単純なデータクラス
- **プロパティ**:
  - `$name`: VM名
  - `$status`: VM状態
  - `$ipAddress`: IPアドレス
  - `$username`: SSHユーザー名
  - `$password`: SSHパスワード
  - `$config`: VM設定（CPU、メモリ、ディスク）

### インターフェース設計

#### HTTP API エンドポイント

```php
POST /create-vm
Content-Type: application/json
{
    "name": "vm-001",
    "user": "user1",
    "cpu": 2,
    "memory": 2048,
    "disk": 20
}

Response:
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

#### Web インターフェース（Slimフレームワーク使用）

- Slimフレームワークベースのシンプルなルーティング
- VM作成用APIエンドポイント
- HTMLフォームとレスポンス表示
- JSON APIとWebフォーム両対応

## データモデル

### SimpleVM クラス（統合データ構造）

```php
class SimpleVM {
    public string $name;
    public string $user; // 'user1', 'user2', 'user3'
    public int $vlanId; // 100, 101, 102
    public string $status; // 'running', 'stopped', 'creating'
    public int $cpu = 2;
    public int $memory = 2048; // MB
    public int $disk = 20; // GB
    public string $ipAddress = '';
    public string $username = 'ubuntu';
    public string $password = '';
    public DateTime $createdAt;
}
```

### システム設定

#### ストレージとネットワーク設定

```php
// デフォルト設定
const DEFAULT_STORAGE_POOL = 'default';
const DEFAULT_ISO_PATH = '/var/lib/libvirt/images/ubuntu-20.04-server-amd64.iso';
const DEFAULT_DISK_PATH = '/var/lib/libvirt/images/';

// ユーザー毎のVLAN ID固定割り当て
const USER_VLAN_MAP = [
    'user1' => 100,
    'user2' => 101,
    'user3' => 102
];
```

#### OpenVSwitchを使用したVLANネットワーク設定

```xml
<!-- OpenVSwitch管理VM -->
<domain type='kvm'>
  <name>openvswitch-manager</name>
  <memory unit='KiB'>1048576</memory>
  <vcpu placement='static'>2</vcpu>
  <devices>
    <interface type='bridge'>
      <source bridge='ovs-br0'/>
      <model type='virtio'/>
    </interface>
  </devices>
</domain>

<!-- OpenVSwitchブリッジネットワーク -->
<network>
  <name>ovs-network</name>
  <forward mode='bridge'/>
  <bridge name='ovs-br0'/>
  <virtualport type='openvswitch'/>
</network>

<!-- ユーザー毎のVLANポート設定 -->
<!-- user1: VLAN 100 -->
<interface type='bridge'>
  <source bridge='ovs-br0'/>
  <virtualport type='openvswitch'>
    <parameters interfaceid='vm-user1-001'/>
  </virtualport>
  <vlan>
    <tag id='100'/>
  </vlan>
</interface>

<!-- user2: VLAN 101 -->
<interface type='bridge'>
  <source bridge='ovs-br0'/>
  <virtualport type='openvswitch'>
    <parameters interfaceid='vm-user2-001'/>
  </virtualport>
  <vlan>
    <tag id='101'/>
  </vlan>
</interface>

<!-- user3: VLAN 102 -->
<interface type='bridge'>
  <source bridge='ovs-br0'/>
  <virtualport type='openvswitch'>
    <parameters interfaceid='vm-user3-001'/>
  </virtualport>
  <vlan>
    <tag id='102'/>
  </vlan>
</interface>
```

#### OpenVSwitchフロールール設定

```bash
# VLAN間通信を禁止するフロールール
ovs-ofctl add-flow ovs-br0 "table=0,priority=100,dl_vlan=100,actions=normal"
ovs-ofctl add-flow ovs-br0 "table=0,priority=100,dl_vlan=101,actions=normal"
ovs-ofctl add-flow ovs-br0 "table=0,priority=100,dl_vlan=102,actions=normal"
ovs-ofctl add-flow ovs-br0 "table=0,priority=50,dl_vlan=100,dl_dst=*,actions=drop"
ovs-ofctl add-flow ovs-br0 "table=0,priority=50,dl_vlan=101,dl_dst=*,actions=drop"
ovs-ofctl add-flow ovs-br0 "table=0,priority=50,dl_vlan=102,dl_dst=*,actions=drop"
```

#### 必要なディレクトリ構造

```
/var/lib/libvirt/images/
├── ubuntu-20.04-server-amd64.iso  # ベースISOイメージ
├── vm-001.qcow2                   # VM用ディスクイメージ
└── vm-002.qcow2                   # VM用ディスクイメージ
```

## エラーハンドリング

### エラー分類

1. **接続エラー**: libvirtデーモン接続失敗
2. **設定エラー**: 無効なVM設定パラメータ
3. **リソースエラー**: 不十分なシステムリソース
4. **作成エラー**: VM作成・起動失敗
5. **ネットワークエラー**: SSH接続情報取得失敗

### エラーレスポンス形式

```php
{
    "success": false,
    "error": {
        "code": "VM_CREATION_FAILED",
        "message": "VMの作成に失敗しました",
        "details": "Insufficient disk space",
        "timestamp": "2025-01-17T10:30:00Z"
    }
}
```

### ログ形式

```
[2025-01-17 10:30:00] ERROR: VM_CREATION_FAILED - VMの作成に失敗しました
Context: {"vm_name": "vm-001", "error": "Insufficient disk space"}
```

## テスト戦略

### 単体テスト

1. **VMManager クラステスト（モック使用）**
   - libvirt関数をモック化したVM作成・起動ロジックのテスト
   - モック化されたlibvirt接続でのエラーハンドリングのテスト
   - 設定検証ロジックのテスト（libvirt呼び出しなし）
   - SSH情報取得ロジックのテスト（ネットワーク呼び出しをモック化）

2. **テストモック戦略**
   - `libvirt_connect()`, `libvirt_domain_define_xml()`, `libvirt_domain_create()`等のlibvirt関数をモック化
   - ファイルシステム操作（ディスクイメージ作成）をモック化
   - ネットワーク操作（SSH接続テスト）をモック化
   - OpenVSwitch操作（ovs-vsctl、ovs-ofctl）をモック化

### 統合テスト

1. **エンドツーエンドテスト**
   - VM作成から起動までの完全フロー
   - SSH情報取得の確認
   - エラーケースの動作確認

2. **libvirt統合テスト**
   - 実際のlibvirtデーモンとの連携テスト
   - VM操作の実行確認

### 開発・テスト環境

#### ローカル開発環境（macOS）
- Docker Composeを使用したlibvirt開発環境
- PHPUnit を使用した自動テストスイート
- モックオブジェクトによる単体テスト
- Dockerコンテナ内でのlibvirt統合テスト
- pre-commitフック（PHPStan、PHP-CS-Fixer、PHPUnit）

#### デプロイ環境（Ubuntu）
- libvirtがプリインストールされたUbuntuサーバー
- SSH経由での手動デプロイ
- 実環境でのlibvirt操作
- Dockerは使用しない

#### GitHub連携・CI/CD
- GitHub Issues による各タスクの進捗管理
- featureブランチ + プルリクエストによる開発フロー
- GitHub Actions による自動テスト・デプロイ
- Conventional Commits によるコミットメッセージ標準化

## セキュリティ考慮事項

1. **入力検証**: すべてのユーザー入力の検証
2. **権限管理**: libvirt操作に必要な適切な権限設定
3. **SSH認証**: 安全なパスワード生成またはキーベース認証
4. **ログセキュリティ**: 機密情報のログ出力回避
5. **ネットワークセキュリティ**: VM間の適切なネットワーク分離

## パフォーマンス考慮事項

1. **非同期処理**: VM作成・起動の非同期実行
2. **タイムアウト管理**: 長時間実行操作のタイムアウト設定
3. **リソース監視**: システムリソース使用量の監視
4. **接続プール**: libvirt接続の効率的な管理