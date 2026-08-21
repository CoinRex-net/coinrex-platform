function createAsyncRoute(jsonError) {
  return function asyncRoute(fn) {
    return (req, res) => Promise.resolve(fn(req, res)).catch((error) => {
      jsonError(res, error.status || 422, error.message);
    });
  };
}

module.exports = createAsyncRoute;
