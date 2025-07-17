**Title:** プロジェクト初期化とGitHub連携セットアップ
**Labels:** setup, infrastructure
**Status:** ✅ COMPLETED

## 概要
プロジェクトの初期化とGitHub連携のセットアップを行います。

## タスク
- [x] GitHubリポジトリの作成とクローン
- [x] pre-commitフック設定（PHPStan、PHP-CS-Fixer、PHPUnit）
- [x] GitHub Actions CI/CDパイプラインの設定
- [x] Conventional Commitsの設定とコミットメッセージテンプレート作成

## 完了条件
- [x] GitHubリポジトリが作成され、適切なREADME.mdが存在すること
- [x] 全実装タスクに対応するGitHub Issueが作成されていること
- [x] `pre-commit install`が成功し、コミット時にフックが実行されること
- [x] GitHub Actionsワークフローファイルが作成され、基本的なCI/CDが動作すること
- [x] コミットメッセージがConventional Commits形式で検証されること

## 関連要件
要件 7.1, 7.2, 7.3, 7.4, 7.5