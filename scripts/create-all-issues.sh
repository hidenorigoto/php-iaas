#!/bin/bash

# VM Management PHP - GitHub Issues Creation Script

echo "🎯 Creating GitHub Issues for VM Management PHP..."
echo "=================================================="

# Check if GitHub CLI is authenticated
if ! gh auth status &> /dev/null; then
    echo "❌ GitHub CLI not authenticated. Please run: gh auth login"
    echo ""
    echo "📋 Alternative: Copy and paste the following issues manually:"
    echo "============================================================="
    
    echo ""
    echo "Issue #1: プロジェクト初期化とGitHub連携セットアップ [COMPLETED]"
    echo "Labels: setup, infrastructure"
    echo "Status: ✅ This issue is already completed"
    
    echo ""
    echo "Issue #2: Docker開発環境とプロジェクト構造のセットアップ"
    echo "Labels: setup, docker, infrastructure"
    echo "Copy content from: scripts/create-github-issues.md (Issue 2 section)"
    
    echo ""
    echo "Issue #3: libvirt-php接続機能の実装"
    echo "Labels: feature, libvirt, connection"
    echo "Copy content from: scripts/create-github-issues.md (Issue 3 section)"
    
    echo ""
    echo "Issue #4: ストレージプールとボリューム管理機能の実装"
    echo "Labels: feature, storage, libvirt"
    echo "Copy content from: scripts/create-github-issues.md (Issue 4 section)"
    
    echo ""
    echo "Issue #5: OpenVSwitchを使用したVLANネットワーク管理機能の実装"
    echo "Labels: feature, network, openvswitch, vlan"
    echo "Copy content from: scripts/create-github-issues.md (Issue 5 section)"
    
    echo ""
    echo "Issue #6: VM設定XML生成機能の実装"
    echo "Labels: feature, xml, configuration"
    echo "Copy content from: scripts/create-github-issues.md (Issue 6 section)"
    
    echo ""
    echo "Issue #7: VM作成・起動機能の実装"
    echo "Labels: feature, vm, creation"
    echo "Copy content from: scripts/create-github-issues.md (Issue 7 section)"
    
    echo ""
    echo "Issue #8: SSH接続情報取得機能の実装"
    echo "Labels: feature, ssh, network"
    echo "Copy content from: scripts/create-github-issues.md (Issue 8 section)"
    
    echo ""
    echo "Issue #9: エラーハンドリングとログ機能の実装"
    echo "Labels: feature, error-handling, logging"
    echo "Copy content from: scripts/create-github-issues.md (Issue 9 section)"
    
    echo ""
    echo "Issue #10: SlimフレームワークでのWebインターフェース作成"
    echo "Labels: feature, web, slim, api"
    echo "Copy content from: scripts/create-github-issues.md (Issue 10 section)"
    
    echo ""
    echo "Issue #11: 統合テストとデバッグ"
    echo "Labels: test, integration, debugging"
    echo "Copy content from: scripts/create-github-issues.md (Issue 11 section)"
    
    echo ""
    echo "📖 Full issue content available in: scripts/create-github-issues.md"
    exit 1
fi

# Create issues using GitHub CLI
echo "✅ GitHub CLI authenticated. Creating issues..."

# Issue 1 - Already completed, create as closed
gh issue create \
    --title "プロジェクト初期化とGitHub連携セットアップ" \
    --body-file scripts/github-issues/issue-01-setup.md \
    --label "setup,infrastructure"

# Close issue 1 immediately since it's completed
ISSUE_1=$(gh issue list --limit 1 --json number --jq '.[0].number')
gh issue close $ISSUE_1 --comment "✅ Task completed during initial setup"

echo "✅ Issue #1 created and closed (completed)"

# Create remaining issues (2-11)
for i in {2..11}; do
    case $i in
        2)
            title="Docker開発環境とプロジェクト構造のセットアップ"
            labels="setup,docker,infrastructure"
            ;;
        3)
            title="libvirt-php接続機能の実装"
            labels="feature,libvirt,connection"
            ;;
        4)
            title="ストレージプールとボリューム管理機能の実装"
            labels="feature,storage,libvirt"
            ;;
        5)
            title="OpenVSwitchを使用したVLANネットワーク管理機能の実装"
            labels="feature,network,openvswitch,vlan"
            ;;
        6)
            title="VM設定XML生成機能の実装"
            labels="feature,xml,configuration"
            ;;
        7)
            title="VM作成・起動機能の実装"
            labels="feature,vm,creation"
            ;;
        8)
            title="SSH接続情報取得機能の実装"
            labels="feature,ssh,network"
            ;;
        9)
            title="エラーハンドリングとログ機能の実装"
            labels="feature,error-handling,logging"
            ;;
        10)
            title="SlimフレームワークでのWebインターフェース作成"
            labels="feature,web,slim,api"
            ;;
        11)
            title="統合テストとデバッグ"
            labels="test,integration,debugging"
            ;;
    esac
    
    echo "Creating Issue #$i: $title"
    # Note: Would need individual body files for each issue
    # For now, reference the main documentation
    gh issue create \
        --title "$title" \
        --body "詳細な内容は scripts/create-github-issues.md の Issue $i セクションを参照してください。" \
        --label "$labels"
    
    echo "✅ Issue #$i created"
done

echo ""
echo "🎉 All GitHub Issues created successfully!"
echo "📋 Next: Review and update issue descriptions with full content from scripts/create-github-issues.md"