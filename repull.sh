if [[ -n $(git status -s) ]]; then
    echo "There are changes in the repository."

    git add .

    echo "Enter commit message: "
    read commit_message
    git commit -m "$commit_message"

    git push origin main
else
    echo "No changes to commit."
fi