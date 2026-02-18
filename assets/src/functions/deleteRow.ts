export async function deleteRow({ id, url }: { id: string; url: string }): Promise<Response> {
  return await fetch(url, {
    method: 'DELETE',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ id }),
  })
}
