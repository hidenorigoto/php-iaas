# GitHub Issues Creation Guide

This document contains the GitHub Issues that should be created for the VM Management PHP project.

## Issues to Create

### Issue 1: プロジェクト初期化とGitHub連携セットアップ
**Title:** プロジェクト初期化とGitHub連携セットアップ
**Labels:** setup, infrastructure
**Body:**
```
## 概要
プロジェクトの初期化とGitHub連携のセットアップを行います。

## タスク
- [x] GitHubリポジトリの作成とクローン
- [x] pre-commitフック設定（PHPStan、PHP-CS-Fixer、PHPUnit）
- [x] GitHub Actions CI/CDパイプラインの設定
- [x] Conventional Commitsの設定とコミットメッセージテンプレート作成

## 完了条件
- [x] GitHubリポジトリが作成され、適切なREADME.mdが存在すること
- [ ] 全実装タスクに対応するGitHub Issueが作成されていること
- [x] `pre-commit install`が成功し、コミット時にフックが実行されること
- [x] GitHub Actionsワークフローファイルが作成され、基本的なCI/CDが動作すること
- [x] コミットメッセージがConventional Commits形式で検証されること

## 関連要件
要件 7.1, 7.2, 7.3, 7.4, 7.5
```

### Issue 2: Docker開発環境とプロジェクト構造のセットアップ
**Title:** Docker開発環境とプロジェクト構造のセットアップ
**Labels:** setup, docker, infrastructure
**Body:**
```
## 概要
Docker Composeを使用したlibvirt開発環境の構築とプロジェクト構造のセットアップを行います。

## タスク
- [ ] Docker Composeを使用したlibvirt開発環境の構築
- [ ] UbuntuベースのDockerコンテナでlibvirt-phpをセットアップ
- [ ] Composerを使用したSlimフレームワークのインストール
- [ ] PHPプロジェクトの基本ディレクトリ構造を作成
- [ ] SimpleVMデータクラスを実装
- [ ] VMManagerクラスの基本構造を作成

## 完了条件
- [ ] `docker compose up`でlibvirt環境が起動すること
- [ ] `composer install`が成功すること
- [ ] PHPUnitとMockeryライブラリがインストールされること
- [ ] SimpleVMクラスのユニットテストが通ること
- [ ] VMManagerクラスのインスタンス化テストが通ること

## 関連要件
要件 1.2, 4.1
```

### Issue 3: libvirt-php接続機能の実装
**Title:** libvirt-php接続機能の実装
**Labels:** feature, libvirt, connection
**Body:**
```
## 概要
libvirt-phpを使用した接続機能を実装します。

## タスク
- [ ] `libvirt_connect()`を使用した接続機能を実装
- [ ] 接続URI（qemu:///system）の設定
- [ ] 接続エラーハンドリングと権限チェックを追加
- [ ] `libvirt_get_last_error()`を使用したエラー情報取得

## 完了条件
- [ ] `libvirt_connect()`をモック化した`VMManager->connect()`メソッドのユニットテストが通ること
- [ ] モック化された接続成功時にリソースが返されることをテストで確認
- [ ] モック化された接続失敗時に適切なエラーメッセージが返されることをテストで確認
- [ ] `VMManager->isConnected()`メソッドが正しく動作することをモックテストで確認

## 関連要件
要件 4.1, 4.2, 4.3, 5.2
```

### Issue 4: ストレージプールとボリューム管理機能の実装
**Title:** ストレージプールとボリューム管理機能の実装
**Labels:** feature, storage, libvirt
**Body:**
```
## 概要
libvirtを使用したストレージプールとボリューム管理機能を実装します。

## タスク
- [ ] `libvirt_storagepool_lookup_by_name()`でストレージプール取得
- [ ] `libvirt_storagevolume_create_xml()`でディスクボリューム作成
- [ ] qcow2形式のディスクイメージ作成機能を実装
- [ ] ベースイメージからのディスクコピー機能を追加

## 完了条件
- [ ] `libvirt_storagepool_lookup_by_name()`をモック化したストレージプール取得のユニットテストが通ること
- [ ] `libvirt_storagevolume_create_xml()`をモック化したディスクボリューム作成のユニットテストが通ること
- [ ] ファイルシステム操作をモック化し、qcow2ファイル作成ロジックのテストが通ること
- [ ] `VMManager->createDiskVolume()`メソッドが成功時にボリュームパスを返すことをモックテストで確認

## 関連要件
要件 1.2, 1.1
```

### Issue 5: OpenVSwitchを使用したVLANネットワーク管理機能の実装
**Title:** OpenVSwitchを使用したVLANネットワーク管理機能の実装
**Labels:** feature, network, openvswitch, vlan
**Body:**
```
## 概要
OpenVSwitchを使用したVLANネットワーク管理機能を実装します。

## タスク
- [ ] 初回VM作成時のOpenVSwitch管理VM自動作成機能を実装
- [ ] OpenVSwitchブリッジ（ovs-br0）の作成と設定
- [ ] user1、user2、user3用の固定VLAN（100、101、102）設定
- [ ] VLAN間通信を禁止するOpenVSwitchフロールールの設定
- [ ] `libvirt_network_define_xml()`を使用したOpenVSwitchネットワーク作成

## 完了条件
- [ ] `libvirt_network_define_xml()`をモック化したOpenVSwitchネットワーク作成のユニットテストが通ること
- [ ] `exec()`関数をモック化し、`ovs-vsctl`、`ovs-ofctl`コマンドの実行ロジックのテストが通ること
- [ ] モック化されたOpenVSwitchコマンドでVLAN分離ルール設定のテストが通ること
- [ ] 各ユーザー用VLAN（100、101、102）のネットワーク定義XML生成のテストが通ること

## 関連要件
要件 5.1, 5.2, 5.3, 5.4, 5.5
```

### Issue 6: VM設定XML生成機能の実装
**Title:** VM設定XML生成機能の実装
**Labels:** feature, xml, configuration
**Body:**
```
## 概要
VM作成用のXML設定生成機能を実装します。

## タスク
- [ ] VM作成用のXML設定テンプレートを作成
- [ ] ストレージボリュームパスとVLANネットワークを含むXML設定生成
- [ ] パラメータに基づくXML設定生成機能を実装
- [ ] デフォルト設定（CPU、メモリ、ディスク、VLAN）の適用

## 完了条件
- [ ] `VMManager->buildVMConfig()`メソッドのユニットテストが通ること
- [ ] 生成されたXMLが有効なlibvirt domain XMLであることをXMLスキーマ検証で確認
- [ ] 各ユーザー（user1、user2、user3）に対して正しいVLAN IDが設定されることをテストで確認
- [ ] デフォルト設定（CPU=2、メモリ=2048MB、ディスク=20GB）が適用されることをテストで確認

## 関連要件
要件 1.2, 1.1, 5.1
```

### Issue 7: VM作成・起動機能の実装
**Title:** VM作成・起動機能の実装
**Labels:** feature, vm, creation
**Body:**
```
## 概要
libvirtを使用したVM作成・起動機能を実装します。

## タスク
- [ ] `libvirt_domain_define_xml()`を使用したVM定義作成
- [ ] `libvirt_domain_create()`を使用したVM起動機能を実装
- [ ] `libvirt_domain_get_info()`でVM状態確認
- [ ] VM作成・起動の成功/失敗判定を追加

## 完了条件
- [ ] `libvirt_domain_define_xml()`、`libvirt_domain_create()`をモック化した`VMManager->createAndStartVM()`メソッドのユニットテストが通ること
- [ ] モック化された`libvirt_domain_get_info()`でVM状態が'running'になることをテストで確認
- [ ] `exec('virsh list --all')`をモック化し、作成されたVMがリストに表示されることをテストで確認
- [ ] VM作成失敗時に適切なエラーメッセージが返されることをモックテストで確認

## 関連要件
要件 1.1, 1.4
```

### Issue 8: SSH接続情報取得機能の実装
**Title:** SSH接続情報取得機能の実装
**Labels:** feature, ssh, network
**Body:**
```
## 概要
VM作成後のSSH接続情報取得機能を実装します。

## タスク
- [ ] `libvirt_domain_get_network_info()`を使用したIPアドレス取得
- [ ] DHCPリース情報からVM IPアドレスを特定
- [ ] SSH認証情報（ユーザー名、パスワード）の生成・管理
- [ ] SSH接続テストによる準備完了確認

## 完了条件
- [ ] `libvirt_domain_get_network_info()`をモック化した`VMManager->getSSHInfo()`メソッドのユニットテストが通ること
- [ ] DHCPリース情報取得をモック化し、IPアドレス取得ロジックのテストが通ること
- [ ] SSH認証情報生成ロジックのユニットテストが通ること
- [ ] `exec('ssh -o ConnectTimeout=5 user@ip echo test')`をモック化し、SSH接続テストロジックのテストが通ること

## 関連要件
要件 1.3, 1.5
```

### Issue 9: エラーハンドリングとログ機能の実装
**Title:** エラーハンドリングとログ機能の実装
**Labels:** feature, error-handling, logging
**Body:**
```
## 概要
包括的なエラーハンドリングとログ機能を実装します。

## タスク
- [ ] 包括的なエラーハンドリング機能を実装
- [ ] ログ記録機能を追加
- [ ] libvirtエラーの適切な処理とメッセージ変換

## 完了条件
- [ ] エラーハンドリングのユニットテストが通ること
- [ ] ログファイルが正しく作成され、エラー情報が記録されることをテストで確認
- [ ] libvirtエラー発生時に適切な日本語エラーメッセージが返されることをテストで確認
- [ ] 無効なパラメータ入力時にバリデーションエラーが返されることをテストで確認

## 関連要件
要件 7.1, 7.2, 7.3, 7.4
```

### Issue 10: SlimフレームワークでのWebインターフェース作成
**Title:** SlimフレームワークでのWebインターフェース作成
**Labels:** feature, web, slim, api
**Body:**
```
## 概要
SlimフレームワークでのWebインターフェースを作成します。

## タスク
- [ ] Slimルーティングの設定とAPIエンドポイント作成
- [ ] VM作成用のシンプルなHTMLフォームを作成
- [ ] JSON APIとWebフォーム両対応の実装
- [ ] SSH接続情報の表示機能を追加

## 完了条件
- [ ] `GET /`でHTMLフォームが表示されることをHTTPテストで確認
- [ ] `POST /create-vm`でJSON APIが正しく動作することをHTTPテストで確認
- [ ] フォーム送信後にSSH接続情報が表示されることをHTTPテストで確認
- [ ] ユーザー選択（user1、user2、user3）が正しく動作することをHTTPテストで確認

## 関連要件
要件 1.3
```

### Issue 11: 統合テストとデバッグ
**Title:** 統合テストとデバッグ
**Labels:** test, integration, debugging
**Body:**
```
## 概要
完全なVM作成・起動フローの統合テストとデバッグを行います。

## タスク
- [ ] 完全なVM作成・起動フローのテストを実装
- [ ] エラーケースのテストを追加
- [ ] 実際のlibvirt環境での動作確認

## 完了条件
- [ ] エンドツーエンド統合テストが全て通ること
- [ ] 各ユーザー（user1、user2、user3）でVM作成からSSH接続まで完全に動作することを確認
- [ ] VLAN分離が正しく動作し、異なるVLAN間の通信が禁止されることをネットワークテストで確認
- [ ] 全てのエラーケース（接続失敗、リソース不足、無効パラメータ等）のテストが通ること
- [ ] PHPUnitテストカバレッジが80%以上であることを確認

## 関連要件
要件 1.1, 1.3, 1.4, 1.5
```

## 作成方法

### 手動作成
1. GitHubリポジトリのIssuesページに移動
2. 各Issueを上記の内容で作成
3. 適切なLabelsを設定
4. Milestoneやプロジェクトボードに追加（必要に応じて）

### GitHub CLI使用（推奨）
```bash
# GitHub CLIをインストール後、以下のコマンドで一括作成
gh issue create --title "プロジェクト初期化とGitHub連携セットアップ" --body-file issue-templates/issue-1.md --label "setup,infrastructure"
gh issue create --title "Docker開発環境とプロジェクト構造のセットアップ" --body-file issue-templates/issue-2.md --label "setup,docker,infrastructure"
# ... 他のIssueも同様に作成
```

## 推奨ラベル設定

以下のラベルをGitHubリポジトリに作成することを推奨します：

- `setup` - セットアップ関連
- `infrastructure` - インフラ関連
- `feature` - 新機能
- `docker` - Docker関連
- `libvirt` - libvirt関連
- `connection` - 接続関連
- `storage` - ストレージ関連
- `network` - ネットワーク関連
- `openvswitch` - OpenVSwitch関連
- `vlan` - VLAN関連
- `xml` - XML設定関連
- `configuration` - 設定関連
- `vm` - VM関連
- `creation` - 作成関連
- `ssh` - SSH関連
- `error-handling` - エラーハンドリング関連
- `logging` - ログ関連
- `web` - Web関連
- `slim` - Slimフレームワーク関連
- `api` - API関連
- `test` - テスト関連
- `integration` - 統合関連
- `debugging` - デバッグ関連

## 注意事項

- 各Issueは実装の順序を考慮して作成されています
- 依存関係がある場合は、Issue内で言及してください
- 進捗に応じてチェックボックスを更新してください
- Issue 1は既に実装済みのため、完了としてマークしてください

## 完了確認

Task 1の完了条件：
- [x] GitHubリポジトリが作成され、適切なREADME.mdが存在すること
- [ ] 全実装タスクに対応するGitHub Issueが作成されていること ← このガイドで対応
- [x] `pre-commit install`が成功し、コミット時にフックが実行されること
- [x] GitHub Actionsワークフローファイルが作成され、基本的なCI/CDが動作すること
- [x] コミットメッセージがConventional Commits形式で検証されること