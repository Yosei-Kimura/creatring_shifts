# creatring_shifts - シフト自動作成プロトタイプ

このフォルダは学園祭用のシフト自動作成の最小実装プロトタイプを含みます。

構成:
- docs/schema.sql - MySQL 用の DDL（スロット粒度 15 分）
- scheduler/heuristic_scheduler.py - Python で動く簡易ヒューリスティック実装
- scheduler/sample_run.py - サンプル実行スクリプト

使い方（ローカル）:
1. Python 3.8+ を使い、scheduler ディレクトリで実行します。

2. サンプルを実行:

```powershell
cd scheduler
python .\sample_run.py
```

MySQL への適用:
- `docs/schema.sql` の SQL を MySQL に適用してください。
- 接続情報が必要な場合は教えてください。SQL を直接出力するようにしました。

次のステップ候補:
- 連続最小/最大時間、休憩ルールのロジックを実装
- 属性フィルタ（position_required_attributes）による候補絞り込み
- 手動オーバーライドAPIのサンプル実装
- ILP 改善フェーズの実装（Python PuLP など）

Web UI (Flask)
-----------------
簡易的なブラウザUIを `scheduler/webapp.py` に追加しました。依存関係に `Flask` を追加しています。
起動例:

```powershell
cd scheduler
pip install -r ../requirements.txt
python .\webapp.py
```

ブラウザで http://localhost:5000 を開くと DB 接続情報を入力してスケジューラを実行できます。結果を DB に保存するオプションもあります。
